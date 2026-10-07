<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Models\User;
use App\Support\Config\SiteConfig;
use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cookie;

/**
 * Auth-cookie helpers extracted from `include/functions.php` (Phase 5
 * of the legacy migration).
 *
 * The legacy procedural helpers
 *
 *   - `logincookie($id, $authKey, $duration)` — builds + sets a signed
 *     auth token cookie.
 *   - `logoutcookie()` — clears the auth cookie.
 *
 * are split into two layers:
 *
 *   1. The **pure token builders** (`buildToken`, `verifyToken`,
 *      `computeExpires`) live here and are testable in isolation.
 *   2. `setLoginCookie()` keeps the legacy side-effects (`setcookie()`,
 *      updating `users.last_login`/`lang`) so call sites can migrate
 *      away from `include/functions.php` directly.
 *
 * `setLoginCookie()` will move to `App\Services\AuthService` in a
 * follow-up Phase 5 PR once the remaining legacy callers are in
 * Laravel-land.
 *
 * Every pure method's contract is pinned by a unit test in
 * `tests/Unit/Support/AuthCookieTest.php`.
 */
final class AuthCookie
{
    /** Cookie name used by the legacy auth system. */
    public const COOKIE_NAME = 'c_secure_pass';

    /**
     * Build the signed auth-token string that goes into the
     * `c_secure_pass` cookie value.
     *
     * New tokens are encrypted with Laravel's encrypter (which uses
     * `APP_KEY`), making them independent of the per-user `auth_key`.
     * The legacy HMAC fallback was removed (ADR 0001, W1-04): only
     * `APP_KEY`-encrypted tokens are accepted.
     *
     * @param  int  $userId  The user's `users.id`
     * @param  string|null  $authKey  Deprecated; no longer used, kept for call-site compatibility
     * @param  int  $expires  Unix timestamp when the cookie expires
     * @param  int  $authVersion  The user's current `users.auth_version`
     */
    public static function buildToken(int $userId, ?string $authKey, int $expires, int $authVersion): string
    {
        if ($authVersion < 1) {
            throw new \InvalidArgumentException('auth_version must be a positive integer');
        }

        $tokenData = [
            'user_id' => $userId,
            'expires' => $expires,
            'auth_version' => $authVersion,
        ];

        return self::encrypter()->encryptString((string) json_encode($tokenData));
    }

    /**
     * Verify and decode a `c_secure_pass` cookie value.
     *
     * Only Laravel-encrypted tokens (signed by `APP_KEY`) are accepted.
     *
     * @param  string  $token  The raw cookie value
     * @return array{user_id: int, expires: int, auth_version: int|null}|null
     */
    public static function verifyToken(string $token): ?array
    {
        try {
            $decrypted = self::encrypter()->decryptString($token);
            $data = json_decode($decrypted, true);
            if (is_array($data)) {
                return self::normalizePayload($data);
            }
        } catch (\RuntimeException) {
            // not an application-encrypted token, or APP_KEY is missing/invalid
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user_id: int, expires: int, auth_version: int|null}|null
     */
    private static function normalizePayload(array $data): ?array
    {
        if (! isset($data['user_id'], $data['expires']) || (int) $data['expires'] < time()) {
            return null;
        }

        $authVersion = null;
        if (array_key_exists('auth_version', $data)) {
            if (! is_int($data['auth_version']) || $data['auth_version'] < 1) {
                return null;
            }
            $authVersion = $data['auth_version'];
        }

        return [
            'user_id' => (int) $data['user_id'],
            'expires' => (int) $data['expires'],
            'auth_version' => $authVersion,
        ];
    }

    /**
     * Compute the expiry timestamp for a login cookie.
     *
     * @param  int  $durationSeconds  Cookie lifetime in seconds (0 = use default 365 days)
     * @param  int  $now  Current unix timestamp (for testability)
     */
    public static function computeExpires(int $durationSeconds, int $now = 0): int
    {
        if ($now === 0) {
            $now = time();
        }

        return $now + $durationSeconds;
    }

    /**
     * Set the legacy `c_secure_pass` cookie and sync `users.last_login`
     * (and `lang` when a language cookie exists).
     *
     * Replaces `logincookie()` from `include/functions.php`. The IO side
     * effects are retained here temporarily until `AuthService` absorbs
     * them.
     *
     * @param  int  $userId  The user's `users.id`
     * @param  string|null  $authKey  Deprecated; no longer used, kept for call-site compatibility
     * @param  int  $durationSeconds  Cookie lifetime in seconds (0 = default 365 days)
     */
    public static function setLoginCookie(int $userId, ?string $authKey = null, int $durationSeconds = 0): void
    {
        if ($durationSeconds <= 0) {
            $durationSeconds = (int) SiteConfig::current()->system->cookieValidDays(365) * 86400;
        }

        $expires = self::computeExpires($durationSeconds);
        $authVersion = self::authRepository()->getAuthVersion($userId);
        if ($authVersion === null || $authVersion < 1) {
            return;
        }
        $token = self::buildToken($userId, null, $expires, $authVersion);

        $secure = Url::isSecure();
        $options = [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::COOKIE_NAME, $token, $options);

        $update = ['last_login' => now()];
        $langId = Locale::idFromCookie((string) '');
        if ($langId > 0) {
            $update['lang'] = $langId;
        }

        self::authRepository()->updateLogin($userId, $update);
    }

    /**
     * Queue the login cookie on the current response via Laravel's cookie jar.
     *
     * Livewire responses do not carry raw `setcookie()` headers, so callers on
     * that path (e.g. the Filament login page) must queue the cookie instead.
     * Unlike setLoginCookie() this does not touch users.last_login — the guard's
     * login() already did that through setLoginCookie().
     */
    public static function queueLoginCookie(int $userId, int $durationSeconds = 0): void
    {
        if ($durationSeconds <= 0) {
            $durationSeconds = (int) SiteConfig::current()->system->cookieValidDays(365) * 86400;
        }

        $expires = self::computeExpires($durationSeconds);
        $authVersion = self::authRepository()->getAuthVersion($userId);
        if ($authVersion === null || $authVersion < 1) {
            return;
        }
        $token = self::buildToken($userId, null, $expires, $authVersion);

        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $token,
            (int) max(1, (int) (($expires - time()) / 60)),
            '/',
            null,
            Url::isSecure(),
            true,
            false,
            'Lax',
        ));
    }

    /**
     * Clear the legacy auth cookie.
     *
     * Mirrors `logoutcookie()`.
     */
    public static function clear(): void
    {
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => Url::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(self::COOKIE_NAME, '', $options);
    }

    /**
     * Lazily create the Laravel encrypter used for new auth tokens.
     *
     * Works outside the full Laravel bootstrap by reading `APP_KEY` from
     * the environment or `.env` file and constructing an `Encrypter` directly.
     */
    private static function encrypter(): Encrypter
    {
        static $encrypter;
        if ($encrypter === null) {
            $key = self::appKey();
            if ($key === '') {
                throw new \RuntimeException('APP_KEY is not set for auth cookie encryption');
            }
            $encrypter = new Encrypter($key, 'aes-256-cbc');
        }

        return $encrypter;
    }

    /**
     * Resolve the application encryption key from config, environment or `.env`.
     */
    private static function appKey(): string
    {
        $candidates = [];

        if (function_exists('config')) {
            try {
                $candidates[] = config('app.key');
            } catch (\Throwable $e) {
                // Laravel not booted, fall back to environment
            }
        }

        $candidates[] = RequestValues::serverValue('APP_KEY', '');
        $envKey = getenv('APP_KEY');
        if ($envKey !== false && $envKey !== '') {
            $candidates[] = $envKey;
        }

        $key = '';
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $key = $candidate;
                break;
            }
        }

        // Last resort: parse .env directly when no Laravel container is booted.
        if ($key === '') {
            $envFile = dirname(__DIR__, 2).'/.env';
            if (file_exists($envFile) && class_exists(Dotenv::class)) {
                try {
                    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
                    $dotenv->safeLoad();
                    $key = RequestValues::serverValue('APP_KEY', '');
                } catch (\Throwable $e) {
                    // ignore .env parse errors
                }
            }
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded === false ? '' : $decoded;
        }

        return $key;
    }

    /**
     * Look up the user from the auth cookie.
     *
     * Accepts the Laravel-encrypted token (signed by `APP_KEY`) only.
     * When `$isArray` is true the row is returned as an array, otherwise
     * an Eloquent User model is returned.
     *
     * @param  array<string, mixed>  $cookie
     * @return array<string, mixed>|User|null
     */
    public static function userFromCookie(array $cookie, bool $isArray = true): array|User|null
    {
        $log = 'cookie: '.json_encode($cookie);
        if (empty($cookie[self::COOKIE_NAME])) {
            Logger::writeWithContext("$log, param not enough");

            return null;
        }

        $token = $cookie[self::COOKIE_NAME];

        $payload = self::verifyToken($token);
        if ($payload === null) {
            return null;
        }

        $log .= ", uid = {$payload['user_id']}";
        $row = self::fetchUser($payload['user_id'], $isArray, $log);
        if ($row === null) {
            return null;
        }
        if (! self::payloadAuthVersionMatches($payload, $row)) {
            Logger::writeWithContext("$log, stale auth_version");

            return null;
        }
        if ($isArray) {
            unset($row['auth_key'], $row['passhash'], $row['auth_version']);
        }

        return $row;
    }

    /**
     * @param  array{user_id: int, expires: int, auth_version: int|null}  $payload
     * @param  array<string, mixed>|User  $user
     */
    private static function payloadAuthVersionMatches(array $payload, array|User $user): bool
    {
        if ($payload['auth_version'] === null) {
            return false;
        }

        $currentVersion = is_array($user)
            ? ($user['auth_version'] ?? null)
            : $user->auth_version;

        return $currentVersion !== null && $payload['auth_version'] === (int) $currentVersion;
    }

    /**
     * Fetch a user by id with the legacy status/enabled checks.
     *
     * @return array<string, mixed>|User|null
     */
    private static function fetchUser(int $id, bool $isArray, string $log)
    {
        $isAjax = RequestContext::instance()->isAjax();
        $selfEnableBonus = SiteConfig::current()->bonus->selfEnable();
        $shouldIgnoreEnabled = LegacyRuntime::instance()->isLegacy() && ! $isAjax && $selfEnableBonus > 0;

        if ($isArray) {
            $row = self::authRepository()->findUserArrayForCookie($id, $shouldIgnoreEnabled);
            if ($row === null) {
                Logger::writeWithContext("$log, user not exists");

                return null;
            }

            return $row;
        }

        $row = self::authRepository()->findUserModelForCookie($id, $shouldIgnoreEnabled);
        if ($row === null) {
            Logger::writeWithContext("$log, user not exists");

            return null;
        }

        return $row;
    }

    private static function authRepository(): AuthRepositoryInterface
    {
        return app(AuthRepositoryInterface::class);
    }
}
