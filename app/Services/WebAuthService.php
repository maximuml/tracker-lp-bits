<?php

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use App\Support\AuthCookie;
use App\Support\Cache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Network;
use App\Support\PasswordHasher;
use App\Support\Token;
use App\Support\TwoFactorAuthHelper;
use Illuminate\Support\Facades\DB;

class WebAuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    private static function getMaxLoginAttempts(): int
    {
        return SiteConfig::fromDb()->security->maxLoginAttempts();
    }

    private static function isCaptchaRequired(): bool
    {
        return SiteConfig::fromDb()->security->captchaRequired() && Captcha::manager()->isEnabled();
    }

    public function isCaptchaEnabled(): bool
    {
        return self::isCaptchaRequired();
    }

    public function maxLoginAttempts(): int
    {
        return self::getMaxLoginAttempts();
    }

    public function remainingAttempts(string $ip): int
    {
        $total = (int) DB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

        return max(0, self::getMaxLoginAttempts() - $total);
    }

    public function assertNotBanned(string $ip): void
    {
        $total = (int) DB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

        if ($total >= self::getMaxLoginAttempts()) {
            DB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['banned' => 'yes']);

            throw new AuthenticationException('Your IP is banned due to too many failed login attempts.');
        }
    }

    /**
     * Validate a user's password without producing side effects.
     *
     * This is used by the stateful guard attempt()/once() methods.
     */
    public function validatePassword(User $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $user->makeVisible(['passhash', 'secret', 'auth_key']);
        $row = $user->toArray();

        $secret = (string) ($row['secret'] ?? '');
        $passhash = (string) ($row['passhash'] ?? '');
        $authKey = (string) ($row['auth_key'] ?? '');
        $algo = (string) ($row['passhash_algo'] ?? PasswordHasher::ALGO_SHA256);

        // For legacy md5 hashes, only verify if auth_key is empty (very old accounts)
        if ($algo === PasswordHasher::ALGO_MD5 && empty($authKey)) {
            return PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_MD5);
        }

        if (! PasswordHasher::verify($password, $passhash, $secret, $algo)) {
            // Fallback: try legacy sha256 if algo wasn't set (pre-migration)
            if ($algo !== PasswordHasher::ALGO_SHA256) {
                return PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_SHA256);
            }

            return false;
        }

        // Upgrade legacy hash to argon2id on successful login
        if (PasswordHasher::needsRehash($algo, $passhash)) {
            $this->upgradePasswordHash((int) $row['id'], $password);
        }

        return true;
    }

    /**
     * Rehash a user's password to argon2id and update the database.
     */
    private function upgradePasswordHash(int $userId, string $password): void
    {
        $newHash = PasswordHasher::hash($password);
        User::query()->where('id', $userId)->update([
            'passhash' => $newHash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function authenticate(array $data, string $ip): User
    {
        $this->assertNotBanned($ip);

        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Username or password invalid.');
        }

        if (self::isCaptchaRequired()) {
            $this->verifyCaptcha($data);
        }

        $user = User::query()
            ->where('username', $username)
            ->first(['id', 'username', 'passhash', 'passhash_algo', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang']);

        if (! $user) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Username or password invalid.');
        }

        $user->makeVisible(['passhash', 'secret', 'auth_key']);
        $row = $user->toArray();

        if ($row['status'] === 'pending') {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Account unconfirmed.');
        }

        if ($row['enabled'] === 'no' && (int) SiteConfig::current()->bonus->selfEnable() <= 0) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Account disabled.');
        }

        if (! empty($row['two_step_secret'])) {
            $code = (string) ($data['two_step_code'] ?? '');
            if ($code === '' || ! TwoFactorAuthHelper::verifyCode($row['two_step_secret'], $code)) {
                $this->recordFailedAttempt($ip);
                throw new AuthenticationException($code === '' ? 'Require two-step code.' : 'Invalid two-step code.');
            }
        }

        if (! $this->validatePassword($user, $password)) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Username or password invalid.');
        }

        $row = $user->toArray();

        // Generate auth_key for very old accounts that don't have one
        $update = [];
        if (empty($row['auth_key'])) {
            $update['auth_key'] = hash('sha256', Token::randomHex(32));
        }

        if (! empty($update)) {
            User::query()->where('id', $row['id'])->update($update);
        }

        $duration = ! empty($data['logout']) && $data['logout'] === 'yes' ? 900 : 0;
        AuthCookie::setLoginCookie((int) $row['id'], null, $duration);

        $this->userRepository->saveLoginLog((int) $row['id'], $ip, 'Web', true);

        Cache::clearUser((int) $row['id'], '');

        return $user;
    }

    public function logout(): void
    {
        AuthCookie::clear();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verifyCaptcha(array $data): void
    {
        $payload = [
            'imagehash' => (string) ($data['imagehash'] ?? ''),
            'imagestring' => (string) ($data['imagestring'] ?? ''),
            'request' => $data,
        ];

        try {
            if (Captcha::manager()->driver()->verify($payload, ['ip' => Network::clientIp()])) {
                return;
            }
        } catch (CaptchaValidationException $exception) {
            throw new AuthenticationException($exception->getMessage());
        }

        throw new AuthenticationException('Invalid captcha response.');
    }

    public function recordFailedAttempt(string $ip): void
    {
        $count = (int) DB::table('loginattempts')->where('ip', $ip)->count();

        if ($count === 0) {
            DB::table('loginattempts')->insert([
                'ip' => $ip,
                'added' => now()->toDateTimeString(),
                'attempts' => 1,
            ]);
        } else {
            DB::table('loginattempts')
                ->where('ip', $ip)
                ->increment('attempts');
        }
    }
}
