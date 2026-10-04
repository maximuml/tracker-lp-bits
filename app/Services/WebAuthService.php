<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\UserAccountRepository;
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
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuthRepositoryInterface $authRepository,
        private readonly UserAccountRepository $userAccountRepository,
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
        $total = $this->authRepository->getLoginAttemptsSum($ip);

        return max(0, self::getMaxLoginAttempts() - $total);
    }

    public function assertNotBanned(string $ip): void
    {
        $total = $this->authRepository->getLoginAttemptsSum($ip);

        if ($total >= self::getMaxLoginAttempts()) {
            $this->authRepository->banLoginAttempts($ip);

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
            $ok = PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_MD5);
            if ($ok) {
                $this->upgradePasswordHash((int) ($row['id'] ?? 0), $password, true);
            }

            return $ok;
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
            $this->upgradePasswordHash((int) ($row['id'] ?? 0), $password, $algo !== PasswordHasher::ALGO_ARGON2ID);
        }

        return true;
    }

    /**
     * Rehash a user's password to argon2id and update the database.
     *
     * @param  bool  $forceChange  stored algo was legacy (md5/sha256) —
     *                             require a password change at next request
     *                             even though the hash was just upgraded
     */
    private function upgradePasswordHash(int $userId, string $password, bool $forceChange = false): void
    {
        $newHash = PasswordHasher::hash($password);
        $this->userAccountRepository->updateById($userId, [
            'passhash' => $newHash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'must_change_password' => $forceChange,
            'auth_version' => DB::raw('auth_version + 1'),
        ]);
        Cache::clearUser($userId, '');
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

        $user = $this->userAccountRepository->findForLogin($username);

        if (! $user) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Username or password invalid.');
        }

        $user->makeVisible(['passhash', 'secret', 'auth_key']);
        $row = $user->toArray();

        if (($row['status'] ?? null) === UserStatus::PENDING->value) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Account unconfirmed.');
        }

        if (! ($row['enabled'] ?? false) && (int) SiteConfig::current()->bonus->selfEnable() <= 0) {
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
            $update['auth_version'] = DB::raw('auth_version + 1');
        }

        if (! empty($update)) {
            $this->userAccountRepository->updateById((int) ($row['id'] ?? 0), $update);
        }

        $duration = ! empty($data['logout']) && $data['logout'] === 'yes' ? 900 : 0;
        AuthCookie::setLoginCookie((int) ($row['id'] ?? 0), null, $duration);

        $this->userRepository->saveLoginLog((int) ($row['id'] ?? 0), $ip, 'Web', true);

        Cache::clearUser((int) ($row['id'] ?? 0), '');

        return $user;
    }

    public function logout(): void
    {
        AuthCookie::clear();
    }

    public function logoutAllDevices(User $user): void
    {
        $this->userAccountRepository->incrementAuthVersion((int) $user->id);
        Cache::clearUser((int) $user->id, '');
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
        $this->authRepository->recordFailedLogin($ip, false);
    }
}
