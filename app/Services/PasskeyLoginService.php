<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Passkey login v2 — HMAC-SHA256 with canonical payload, nonce replay
 * protection, and key rotation by key ID.
 *
 * Canonical payload format (JSON, sorted keys):
 *   {
 *     "v": 2,                         // protocol version
 *     "kid": "key1",                  // signing key ID
 *     "pk": "<32-char hex passkey>",  // passkey fingerprint
 *     "ts": 1700000000,               // unix timestamp (seconds)
 *     "nonce": "<32-char hex>",       // unique per request
 *     "action": "login"               // action scope
 *   }
 *
 * signature = HMAC-SHA256(canonical_json, signing_key)
 *
 * The timestamp must be within ±5 minutes of server time.
 * Each nonce can only be used once — an atomic cache "add" prevents replay.
 *
 * Key rotation: two keys (current + previous) are accepted during the
 * overlap window. The key ID in the payload selects which key to use; the
 * previous key additionally requires a future `previous_key_deadline`
 * setting so the overlap cannot silently stay open forever.
 */
final class PasskeyLoginService
{
    /** Protocol version. */
    public const VERSION = 2;

    /** Timestamp tolerance window in seconds (±5 minutes). */
    public const TIMESTAMP_WINDOW = 300;

    /**
     * Extra seconds added to the nonce marker lifetime so it outlives the
     * signature validity by a small margin (boundary/clock races).
     */
    public const NONCE_TTL_GRACE = 10;

    /** Default action scope. */
    public const ACTION_LOGIN = 'login';

    /** Redis key prefix for nonce replay protection. */
    private const NONCE_KEY_PREFIX = 'passkey_login_v2:nonce:';

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Verify a v2 passkey login payload and signature.
     *
     * Returns true if the signature is valid, the timestamp is within
     * the window, and the nonce has not been used before.
     *
     * @param  string  $passkey  32-char hex passkey.
     * @param  int  $timestamp  Unix timestamp (seconds).
     * @param  string  $nonce  32-char hex nonce.
     * @param  string  $signature  HMAC-SHA256 hex signature.
     * @param  string  $keyId  Signing key ID.
     * @param  string  $action  Action scope (default: "login").
     */
    public function verify(
        string $passkey,
        int $timestamp,
        string $nonce,
        string $signature,
        string $keyId,
        string $action = self::ACTION_LOGIN,
    ): bool {
        // 1. Enforce the action scope — a payload signed for another action
        // must not authenticate a login.
        if ($action !== self::ACTION_LOGIN) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: unsupported action scope "%s"', $action),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 2. Validate timestamp window
        $now = time();
        if (abs($now - $timestamp) > self::TIMESTAMP_WINDOW) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: timestamp out of window (server=%d, client=%d)', $now, $timestamp),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 3. Resolve signing key by key ID
        $signingKey = $this->resolveSigningKey($keyId);
        if ($signingKey === null) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: unknown key ID "%s"', $keyId),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 4. Build canonical payload and verify HMAC
        $canonical = $this->canonicalPayload($passkey, $timestamp, $nonce, $keyId, $action);
        $expected = hash_hmac('sha256', $canonical, $signingKey);

        if (! hash_equals($expected, $signature)) {
            Logger::writeWithContext(
                (string) 'passkeyLoginV2: invalid HMAC signature',
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 5. Nonce replay protection — atomic "set if not exists" via add().
        // add() returns true if the key was set (first use), false if it
        // already existed (replay). The marker must live at least as long
        // as the signature remains acceptable: a payload signed at the far
        // (future) edge of the timestamp window stays valid until
        // ts + TIMESTAMP_WINDOW, so the TTL is the *remaining* signature
        // lifetime, not a fixed 300 s from arrival — otherwise the marker
        // would expire while the signature is still replayable.
        // A failing store must reject the login (fail closed): silently
        // proceeding would disable replay protection unnoticed.
        $nonceKey = self::NONCE_KEY_PREFIX.hash('sha256', $nonce.$keyId);
        $ttl = self::nonceTtlSeconds($timestamp, $now);

        try {
            $stored = $this->cache->add($nonceKey, '1', now()->addSeconds($ttl));
        } catch (\Throwable $e) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: nonce store unavailable (%s) — rejecting login', $e->getMessage()),
                (string) 'error',
                (bool) false,
            );

            return false;
        }

        if ($stored === false) {
            Logger::writeWithContext(
                (string) 'passkeyLoginV2: replay detected — nonce already used',
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        return true;
    }

    /**
     * Seconds a nonce marker must live so it cannot expire while the
     * signature it guards is still acceptable. The signature is valid
     * until `timestamp + TIMESTAMP_WINDOW`, so the remaining lifetime is
     * `timestamp + window - now`; a small grace covers boundary races.
     * Always ≥ 1 so a marker at the exact window edge is still written.
     */
    public static function nonceTtlSeconds(int $timestamp, int $now): int
    {
        return max(1, ($timestamp + self::TIMESTAMP_WINDOW) - $now + self::NONCE_TTL_GRACE);
    }

    /**
     * Build the canonical JSON payload for signing/verification.
     *
     * Keys are sorted alphabetically to ensure deterministic output.
     */
    public function canonicalPayload(
        string $passkey,
        int $timestamp,
        string $nonce,
        string $keyId,
        string $action,
    ): string {
        $payload = [
            'action' => $action,
            'kid' => $keyId,
            'nonce' => $nonce,
            'pk' => $passkey,
            'ts' => $timestamp,
            'v' => self::VERSION,
        ];
        ksort($payload);

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve the signing key by key ID.
     *
     * Supports key rotation: "current" key is preferred, "previous"
     * key is accepted until `passkey_login_previous_key_deadline`.
     */
    private function resolveSigningKey(string $keyId): ?string
    {
        $keys = $this->signingKeys();

        return $keys[$keyId] ?? null;
    }

    /**
     * Get all valid signing keys: [keyId => signingKey].
     *
     * Reads from SiteConfig security settings:
     * - passkey_login_signing_key_current (with key_id_current)
     * - passkey_login_signing_key_previous (with key_id_previous) —
     *   accepted only while passkey_login_previous_key_deadline is in the
     *   future; a configured previous key without a deadline is rejected,
     *   so the rotation overlap cannot silently stay open-ended.
     *
     * @return array<string, string>
     */
    private function signingKeys(): array
    {
        $security = SiteConfig::current()->security;
        $keys = [];

        $currentKey = $security->passkeyLoginSigningKeyCurrent();
        $currentKeyId = $security->passkeyLoginSigningKeyIdCurrent();
        if ($currentKey !== '' && $currentKeyId !== '') {
            $keys[$currentKeyId] = $currentKey;
        }

        $previousKey = $security->passkeyLoginSigningKeyPrevious();
        $previousKeyId = $security->passkeyLoginSigningKeyIdPrevious();
        $previousDeadline = $security->passkeyLoginPreviousKeyDeadline();
        if (
            $previousKey !== ''
            && $previousKeyId !== ''
            && $previousDeadline !== null
            && $previousDeadline > now()->toDateTimeString()
        ) {
            $keys[$previousKeyId] = $previousKey;
        }

        return $keys;
    }
}
