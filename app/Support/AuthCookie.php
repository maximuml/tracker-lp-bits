<?php

namespace App\Support;

use App\Models\User;
use Nexus\Database\NexusDB;

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

    /**
     * Clear the legacy auth cookie.
     *
     * Mirrors `logoutcookie()`.
     */
    public static function clear(): void
    {
        setcookie(self::COOKIE_NAME, '', 0x7fffffff, '/', '', isHttps(), true);
    }

    /**
     * Resolve a tracker-report authkey to the user's passkey.
     *
     * Mirrors `get_passkey_by_authkey()`. The result is cached for 24h.
     */
    public static function passkeyByAuthkey(string $authkey): string
    {
        return \Nexus\Database\NexusDB::remember("authkey2passkey:$authkey", 3600 * 24, function () use ($authkey) {
            $arr = explode('|', $authkey);
            if (count($arr) !== 3) {
                throw new \InvalidArgumentException("Invalid authkey: $authkey, format error");
            }
            $uid = $arr[1];
            $decrypted = (new \App\Repositories\TorrentRepository())->checkTrackerReportAuthKey($authkey);
            if (empty($decrypted)) {
                throw new \InvalidArgumentException("Invalid authkey: $authkey");
            }
            $userInfo = \Nexus\Database\NexusDB::remember("announce_user_passkey_$uid", 3600, function () use ($uid) {
                return \App\Models\User::query()->where('id', $uid)->first(['id', 'passkey']);
            });

            return $userInfo === null ? '' : $userInfo->passkey;
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
        $log = 'cookie: ' . json_encode($cookie);
        if (empty($cookie[self::COOKIE_NAME])) {
            \do_log("$log, param not enough");
            return null;
        }

        $base64Decoded = base64_decode($cookie[self::COOKIE_NAME]);
        if (empty($base64Decoded)) {
            \do_log("$log, invalid c_secure_pass");
            return null;
        }

        $log .= ", base64 decoded: $base64Decoded";
        $tokenJsonAndSignature = explode('.', $base64Decoded);
        if (count($tokenJsonAndSignature) !== 2) {
            \do_log("$log, invalid c_secure_pass base64_decoded");
            return null;
        }

        $tokenJson = $tokenJsonAndSignature[0];
        $signature = $tokenJsonAndSignature[1];
        if (empty($tokenJson) || empty($signature)) {
            \do_log("$log, no tokenJson or signature");
            return null;
        }

        $tokenData = json_decode($tokenJson, true);
        if (!isset($tokenData['user_id'])) {
            \do_log("$log, no user_id");
            return null;
        }
        if (!isset($tokenData['expires']) || $tokenData['expires'] < time()) {
            \do_log("$log, signature expired");
            return null;
        }

        return [
            'user_id' => (int) $tokenData['user_id'],
            'token_json' => $tokenJson,
            'signature' => $signature,
        ];
    }

    /**
     * Look up the user from the legacy auth cookie.
     *
     * Mirrors `get_user_from_cookie()`. When `$isArray` is true the row is
     * returned as an array, otherwise an Eloquent User model is returned.
     *
     * @param  array<string, mixed>  $cookie
     * @return array<string, mixed>|\App\Models\User|null
     */
    public static function userFromCookie(array $cookie, bool $isArray = true): array|\App\Models\User|null
    {
        $log = 'cookie: ' . json_encode($cookie);
        $result = self::decodeCookie($cookie);
        if (empty($result)) {
            return null;
        }

        $id = $result['user_id'];
        $tokenJson = $result['token_json'];
        $signature = $result['signature'];
        $log .= ", uid = $id";
        $isAjax = \nexus()->isAjax();
        $selfEnableBonus = \App\Models\Setting::getSelfEnableBonus();
        $shouldIgnoreEnabled = defined('IN_NEXUS') && IN_NEXUS && !$isAjax && $selfEnableBonus > 0;

        if ($isArray) {
            $query = NexusDB::table('users')
                ->where('id', $id)
                ->where('status', 'confirmed');
            if (!$shouldIgnoreEnabled) {
                $query->where('enabled', 'yes');
            }
            $result = $query->first();
            $row = $result ? array_merge((array) $result, array_values((array) $result)) : null;
            if (!$row) {
                \do_log("$log, user not exists");
                return null;
            }
            $authKey = $row['auth_key'];
            unset($row['auth_key'], $row['passhash']);
        } else {
            $row = \App\Models\User::query()->find($id);
            if (!$row) {
                \do_log("$log, user not exists");
                return null;
            }
            $checkFields = ['status'];
            if (!$shouldIgnoreEnabled) {
                $checkFields[] = 'enabled';
            }
            $row->checkIsNormal($checkFields);
            $authKey = $row->auth_key;
        }

        $expectedSignature = hash_hmac('sha256', $tokenJson, $authKey);
        if (!hash_equals($expectedSignature, $signature)) {
            \do_log("$log, !hash_equals, expectedSignature: $expectedSignature, actualSignature: $signature");
            return null;
        }

        return $row;
    }
}
