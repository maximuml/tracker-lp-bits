<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Illuminate\Support\Facades\Cache;

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
 * Each nonce can only be used once — Redis SET NX EX 300 prevents replay.
 *
 * Key rotation: two keys (current + previous) are accepted during the
 * overlap window. The key ID in the payload selects which key to use.
 */
final class PasskeyLoginService
{
    /** Protocol version. */
    public const VERSION = 2;

    /** Timestamp tolerance window in seconds (±5 minutes). */
    public const TIMESTAMP_WINDOW = 300;

    /** Nonce cache TTL in seconds (matches timestamp window). */
    public const NONCE_TTL = 300;

    /** Default action scope. */
    public const ACTION_LOGIN = 'login';

    /** Redis key prefix for nonce replay protection. */
    private const NONCE_KEY_PREFIX = 'passkey_login_v2:nonce:';

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
        // 1. Validate timestamp window
        $now = time();
        if (abs($now - $timestamp) > self::TIMESTAMP_WINDOW) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: timestamp out of window (server=%d, client=%d)', $now, $timestamp),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 2. Resolve signing key by key ID
        $signingKey = $this->resolveSigningKey($keyId);
        if ($signingKey === null) {
            Logger::writeWithContext(
                (string) sprintf('passkeyLoginV2: unknown key ID "%s"', $keyId),
                (string) 'warning',
                (bool) false,
            );

            return false;
        }

        // 3. Build canonical payload and verify HMAC
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

        // 4. Nonce replay protection — atomic "set if not exists" via Cache::add()
        // Cache::add() returns true if the key was set (first use), false if it
        // already existed (replay). TTL matches the timestamp window.
        $nonceKey = self::NONCE_KEY_PREFIX.hash('sha256', $nonce.$keyId);
        $stored = Cache::add($nonceKey, '1', now()->addSeconds(self::NONCE_TTL));
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
     * key is accepted during the overlap window.
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
     * - passkey_login_signing_key_previous (with key_id_previous)
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
        if ($previousKey !== '' && $previousKeyId !== '') {
            $keys[$previousKeyId] = $previousKey;
        }

        return $keys;
    }
}
