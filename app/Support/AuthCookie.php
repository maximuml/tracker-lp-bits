<?php

namespace App\Support;

use App\Models\User;

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
     * Token format: `base64(json({"user_id":N,"expires":T}) . "." . hmac_sha256)`
     *
     * The HMAC is computed over the JSON payload using the user's
     * `auth_key` as the secret. This is the exact shape the legacy
     * `logincookie()` produces.
     *
     * @param  int  $userId  The user's `users.id`
     * @param  string  $authKey  The user's `users.auth_key` (HMAC secret)
     * @param  int  $expires  Unix timestamp when the cookie expires
     *
     * @throws \RuntimeException if `$authKey` is empty
     */
    public static function buildToken(int $userId, string $authKey, int $expires): string
    {
        if ($authKey === '') {
            throw new \RuntimeException('auth_key is empty');
        }

        $tokenData = [
            'user_id' => $userId,
            'expires' => $expires,
        ];
        $tokenJson = json_encode($tokenData);
        $signature = hash_hmac('sha256', $tokenJson, $authKey);

        return base64_encode($tokenJson.'.'.$signature);
    }

    /**
     * Verify and decode a `c_secure_pass` cookie value.
     *
     * Returns the decoded payload `['user_id' => int, 'expires' => int]`
     * on success, or `null` if the token is malformed, the signature
     * is invalid, or the token has expired.
     *
     * @param  string  $token  The raw cookie value
     * @param  string  $authKey  The user's `users.auth_key` for HMAC verification
     * @return array{user_id: int, expires: int}|null
     */
    public static function verifyToken(string $token, string $authKey): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $dotPos = strrpos($decoded, '.');
        if ($dotPos === false) {
            return null;
        }

        $tokenJson = substr($decoded, 0, $dotPos);
        $signature = substr($decoded, $dotPos + 1);

        $expectedSignature = hash_hmac('sha256', $tokenJson, $authKey);
        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $data = json_decode($tokenJson, true);
        if (! is_array($data) || ! isset($data['user_id'], $data['expires'])) {
            return null;
        }

        if ((int) $data['expires'] < time()) {
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
     * @param  string  $authKey  The user's `users.auth_key`
     * @param  int  $durationSeconds  Cookie lifetime in seconds (0 = default 365 days)
     */
    public static function setLoginCookie(int $userId, string $authKey, int $durationSeconds = 0): void
    {
        if ($authKey === '') {
            throw new \RuntimeException('auth_key is empty');
        }

        if ($durationSeconds <= 0) {
            $durationSeconds = (int) get_setting('system.cookie_valid_days', 365) * 86400;
        }

        $expires = self::computeExpires($durationSeconds);
        $token = self::buildToken($userId, $authKey, $expires);

        setcookie(self::COOKIE_NAME, $token, $expires, '/', '', isHttps(), true);

        $update = ['last_login' => now()];
        $langId = get_langid_from_langcookie();
        if ($langId > 0) {
            $update['lang'] = $langId;
        }

        User::query()->where('id', $userId)->update($update);
    }
}
