<?php

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\AuthCookie;
use App\Support\Captcha;
use App\Support\Network;
use App\Support\Token;
use App\Support\TwoFactorAuthHelper;
use Nexus\Database\NexusDB;

class WebAuthService
{
    private static function getMaxLoginAttempts(): int
    {
        return (int) Setting::getFromDb('security.maxloginattempts', 10);
    }

    private static function isCaptchaRequired(): bool
    {
        return Setting::getFromDb('security.iv', 'no') === 'yes' && Captcha::manager()->isEnabled();
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
        $total = (int) NexusDB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

        return max(0, self::getMaxLoginAttempts() - $total);
    }

    public function assertNotBanned(string $ip): void
    {
        $total = (int) NexusDB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

        if ($total >= self::getMaxLoginAttempts()) {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['banned' => 'yes']);

            throw new AuthenticationException('Your IP is banned due to too many failed login attempts.');
        }
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

        /** @var User|null $user */
        $user = User::query()
            ->where('username', $username)
            ->first(['id', 'username', 'passhash', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang']);

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

        if ($row['enabled'] === 'no' && (int) Setting::getSelfEnableBonus() <= 0) {
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

        $passwordHash = hash('sha256', $row['secret'] . hash('sha256', $password));
        $update = [];

        if (empty($row['auth_key'])) {
            $oldMd5 = md5($row['secret'] . $password . $row['secret']);
            if (! hash_equals($oldMd5, $row['passhash'])) {
                $this->recordFailedAttempt($ip);
                throw new AuthenticationException('Username or password invalid.');
            }
            $update['passhash'] = $row['passhash'] = $passwordHash;
        }

        $challenge = Token::randomHex();
        $expected = hash_hmac('sha256', $row['passhash'], $challenge);
        $response = hash_hmac('sha256', $passwordHash, $challenge);
        if (! hash_equals($expected, $response)) {
            $this->recordFailedAttempt($ip);
            throw new AuthenticationException('Username or password invalid.');
        }

        if (empty($row['auth_key'])) {
            $update['auth_key'] = hash('sha256', Token::randomHex(32));
        }

        if (! empty($update)) {
            User::query()->where('id', $row['id'])->update($update);
        }

        $duration = ! empty($data['logout']) && $data['logout'] === 'yes' ? 900 : 0;
        AuthCookie::setLoginCookie((int) $row['id'], null, $duration);

        (new UserRepository())->saveLoginLog((int) $row['id'], $ip, 'Web', false);

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
        } catch (\App\Services\Captcha\Exceptions\CaptchaValidationException $exception) {
            throw new AuthenticationException($exception->getMessage());
        }

        throw new AuthenticationException('Invalid captcha response.');
    }

    private function recordFailedAttempt(string $ip): void
    {
        $count = (int) NexusDB::table('loginattempts')->where('ip', $ip)->count();

        if ($count === 0) {
            NexusDB::table('loginattempts')->insert([
                'ip' => $ip,
                'added' => now()->toDateTimeString(),
                'attempts' => 1,
            ]);
        } else {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['attempts' => NexusDB::raw('attempts + 1')]);
        }
    }
}
