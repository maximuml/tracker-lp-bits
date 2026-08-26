<?php

namespace App\Support;

use App\Models\User;
use App\Repositories\AuthRepository;
use App\Repositories\TorrentRepository;
use App\Support\Config\SiteConfig;
use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Nexus\Nexus;

/**
 * Auth-cookie helpers extracted from `include/functions.php` (Phase 5
 * of the legacy migration — see `docs/legacy-strategy.md` § "Phase 5 —
 * drain `include/functions.php`").
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
     * Legacy HMAC tokens are still accepted by `verifyToken()` for the
     * lifetime of the existing cookie.
     *
     * @param  int  $userId  The user's `users.id`
     * @param  string|null  $authKey  Deprecated; no longer used, kept for call-site compatibility
     * @param  int  $expires  Unix timestamp when the cookie expires
     */
    public static function buildToken(int $userId, ?string $authKey, int $expires): string
    {
        $tokenData = [
            'user_id' => $userId,
            'expires' => $expires,
        ];

        return self::encrypter()->encryptString((string) json_encode($tokenData));
    }

    /**
     * Verify and decode a `c_secure_pass` cookie value.
     *
     * First tries the new Laravel-encrypted token (signed by `APP_KEY`).
     * If that fails and `$authKey` is provided, falls back to the legacy
     * HMAC token signed with the user's `auth_key`.
     *
     * @param  string  $token  The raw cookie value
     * @param  string|null  $authKey  The user's `users.auth_key` for legacy HMAC verification
     * @return array{user_id: int, expires: int}|null
     */
    public static function verifyToken(string $token, ?string $authKey = null): ?array
    {
        try {
            $decrypted = self::encrypter()->decryptString($token);
            $data = json_decode($decrypted, true);
            if (is_array($data) && isset($data['user_id'], $data['expires']) && (int) $data['expires'] >= time()) {
                return [
                    'user_id' => (int) $data['user_id'],
                    'expires' => (int) $data['expires'],
                ];
            }
        } catch (\RuntimeException $e) {
            // not an application-encrypted token, or APP_KEY is missing/invalid;
            // try legacy HMAC below
        }

        if ($authKey === null || $authKey === '') {
            return null;
        }

        $legacy = self::decodeCookie([self::COOKIE_NAME => $token]);
        if ($legacy === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $legacy['token_json'], $authKey);
        if (! hash_equals($expectedSignature, $legacy['signature'])) {
            return null;
        }

        $data = json_decode($legacy['token_json'], true);
        if (! is_array($data) || ! isset($data['user_id'], $data['expires']) || (int) $data['expires'] < time()) {
            return null;
        }

        return [
            'user_id' => (int) $data['user_id'],
            'expires' => (int) $data['expires'],
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
        $token = self::buildToken($userId, null, $expires);

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

        AuthRepository::updateLogin($userId, $update);
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

        $candidates[] = Input::serverValue('APP_KEY', '');
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
                    $key = Input::serverValue('APP_KEY', '');
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
     * Resolve a tracker-report authkey to the user's passkey.
     *
     * Mirrors `get_passkey_by_authkey()`. The result is cached for 24h.
     */
    public static function passkeyByAuthkey(string $authkey): string
    {
        return Cache::remember("authkey2passkey:$authkey", 3600 * 24, function () use ($authkey) {
            $arr = explode('|', $authkey);
            if (count($arr) !== 3) {
                throw new \InvalidArgumentException("Invalid authkey: $authkey, format error");
            }
            $uid = $arr[1];
            $decrypted = (new TorrentRepository)->checkTrackerReportAuthKey($authkey);
            if (empty($decrypted)) {
                throw new \InvalidArgumentException("Invalid authkey: $authkey");
            }

            return AuthRepository::getPasskeyByUserId((int) $uid) ?? '';
        });
    }

    /**
     * Decode the signed user cookie value (c_secure_pass).
     *
     * Mirrors `get_user_id_and_signature_from_cookie()`.
     *
     * @param  array<string, mixed>  $cookie
     * @return array{user_id: int, token_json: string, signature: string}|null
     */
    public static function decodeCookie(array $cookie): ?array
    {
        $log = 'cookie: '.json_encode($cookie);
        if (empty($cookie[self::COOKIE_NAME])) {
            Logger::writeWithContext("$log, param not enough");

            return null;
        }

        $base64Decoded = base64_decode($cookie[self::COOKIE_NAME]);
        if (empty($base64Decoded)) {
            Logger::writeWithContext("$log, invalid c_secure_pass");

            return null;
        }

        $log .= ", base64 decoded: $base64Decoded";
        $tokenJsonAndSignature = explode('.', $base64Decoded);
        if (count($tokenJsonAndSignature) !== 2) {
            Logger::writeWithContext("$log, invalid c_secure_pass base64_decoded");

            return null;
        }

        $tokenJson = $tokenJsonAndSignature[0];
        $signature = $tokenJsonAndSignature[1];
        if (empty($tokenJson) || empty($signature)) {
            Logger::writeWithContext("$log, no tokenJson or signature");

            return null;
        }

        $tokenData = json_decode($tokenJson, true);
        if (! isset($tokenData['user_id'])) {
            Logger::writeWithContext("$log, no user_id");

            return null;
        }
        if (! isset($tokenData['expires']) || $tokenData['expires'] < time()) {
            Logger::writeWithContext("$log, signature expired");

            return null;
        }

        return [
            'user_id' => (int) $tokenData['user_id'],
            'token_json' => $tokenJson,
            'signature' => $signature,
        ];
    }

    /**
     * Look up the user from the auth cookie.
     *
     * Accepts both the new Laravel-encrypted token (signed by `APP_KEY`)
     * and the legacy HMAC token (signed by the user's `auth_key`).
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

        // New Laravel-encrypted token is verified without a per-user secret.
        $payload = self::verifyToken($token);
        if ($payload !== null) {
            $log .= ", uid = {$payload['user_id']} (app encrypted)";
            $row = self::fetchUser($payload['user_id'], $isArray, $log);
            if ($row !== null && $isArray) {
                unset($row['auth_key'], $row['passhash']);
            }

            return $row;
        }

        // Legacy HMAC token: decode first to get the user id, then load
        // the user and verify the signature against the stored auth_key.
        $result = self::decodeCookie($cookie);
        if (empty($result)) {
            return null;
        }

        $id = $result['user_id'];
        $log .= ", uid = $id (legacy)";

        $row = self::fetchUser($id, $isArray, $log);
        if ($row === null) {
            return null;
        }

        if (is_array($row)) {
            $authKey = (string) ($row['auth_key'] ?? '');
        } else {
            $authKey = (string) $row->auth_key;
        }
        if (self::verifyToken($token, $authKey) === null) {
            Logger::writeWithContext("$log, !hash_equals");

            return null;
        }

        if ($isArray) {
            unset($row['auth_key'], $row['passhash']);
        }

        return $row;
    }

    /**
     * Fetch a user by id with the legacy status/enabled checks.
     *
     * @return array<string, mixed>|User|null
     */
    private static function fetchUser(int $id, bool $isArray, string $log)
    {
        $isAjax = Nexus::instance()->isAjax();
        $selfEnableBonus = SiteConfig::current()->bonus->selfEnable();
        $shouldIgnoreEnabled = defined('IN_NEXUS') && IN_NEXUS && ! $isAjax && $selfEnableBonus > 0;

        if ($isArray) {
            $row = AuthRepository::findUserArrayForCookie($id, $shouldIgnoreEnabled);
            if ($row === null) {
                Logger::writeWithContext("$log, user not exists");

                return null;
            }

            return $row;
        }

        $row = AuthRepository::findUserModelForCookie($id, $shouldIgnoreEnabled);
        if ($row === null) {
            Logger::writeWithContext("$log, user not exists");

            return null;
        }

        return $row;
    }
}
