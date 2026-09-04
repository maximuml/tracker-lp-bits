<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Logger;
use App\Support\Network;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Secure third-party authentication for IYUU, NAS Tools, and AMMDS integrations.
 *
 * Replaces the legacy per-integration auth with a unified, versioned protocol:
 *
 * - HMAC-SHA256 signatures (not MD5)
 * - hash_equals for constant-time comparison
 * - Timestamp ±5 minute window
 * - Nonce replay protection via Cache::add()
 * - Audit log entries without secrets
 *
 * Protocol version 2 (v2):
 *   signature = HMAC-SHA256(canonical_payload, integration_secret)
 *   canonical_payload = "{provider}|{uid}|{passkey_hash}|{timestamp}|{nonce}|{v2}"
 *
 * Legacy protocols are still accepted for backward compatibility.
 */
class ThirdPartyAuthService
{
    /** Protocol version. */
    public const VERSION = 'v2';

    /** Timestamp window in seconds (±5 minutes). */
    public const TIMESTAMP_WINDOW = 300;

    /** Nonce cache TTL in seconds. */
    public const NONCE_TTL = 600;

    /** Integration provider names. */
    public const PROVIDER_IYUU = 'iyuu';

    public const PROVIDER_NAS_TOOLS = 'nastools';

    public const PROVIDER_AMMDS = 'ammds';

    public function __construct() {}

    /**
     * Verify a v2 HMAC-SHA256 signature for a third-party integration.
     *
     * @param  string  $provider  Integration provider (iyuu, nastools, ammds).
     * @param  int  $userId  User ID.
     * @param  string  $passkey  User's passkey (32-char hex).
     * @param  int  $timestamp  Unix timestamp (seconds).
     * @param  string  $nonce  Unique nonce per request.
     * @param  string  $signature  HMAC-SHA256 signature (64-char hex).
     * @param  string  $secret  Integration secret key.
     * @return User|null The authenticated user, or null if verification fails.
     */
    public function verifyV2(
        string $provider,
        int $userId,
        string $passkey,
        int $timestamp,
        string $nonce,
        string $signature,
        string $secret,
    ): ?User {
        // 1. Timestamp window check
        $now = time();
        if (abs($now - $timestamp) > self::TIMESTAMP_WINDOW) {
            $this->audit($provider, $userId, 'expired_timestamp', [
                'server_time' => $now,
                'client_time' => $timestamp,
            ]);

            return null;
        }

        // 2. Nonce replay protection — atomic set-if-not-exists
        $nonceKey = $this->nonceCacheKey($provider, $nonce);
        if (Cache::add($nonceKey, '1', now()->addSeconds(self::NONCE_TTL)) === false) {
            $this->audit($provider, $userId, 'replay_detected', [
                'nonce' => $nonce,
            ]);

            return null;
        }

        // 3. Find user
        $user = User::query()->find($userId, User::$commonFields);
        if (! $user instanceof User) {
            $this->audit($provider, $userId, 'user_not_found', []);

            return null;
        }

        try {
            $user->checkIsNormal();
        } catch (\Throwable $e) {
            $this->audit($provider, $userId, 'user_not_normal', []);

            return null;
        }

        // 4. Verify passkey matches
        if ((string) $user->passkey !== $passkey) {
            $this->audit($provider, $userId, 'passkey_mismatch', []);

            return null;
        }

        // 5. Compute and verify HMAC-SHA256 signature
        $passkeyHash = hash('sha256', $passkey);
        $canonical = $this->canonicalPayload($provider, $userId, $passkeyHash, $timestamp, $nonce);
        $expected = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($expected, $signature)) {
            $this->audit($provider, $userId, 'invalid_signature', []);

            return null;
        }

        // 6. Audit success
        $this->audit($provider, $userId, 'success', []);

        return $user;
    }

    /**
     * Build the canonical payload string for HMAC signing.
     */
    public function canonicalPayload(
        string $provider,
        int $userId,
        string $passkeyHash,
        int $timestamp,
        string $nonce,
    ): string {
        return implode('|', [
            $provider,
            $userId,
            $passkeyHash,
            $timestamp,
            $nonce,
            self::VERSION,
        ]);
    }

    /**
     * Legacy IYUU verification (MD5-based, backward compatibility).
     *
     * @param  string  $token  IYUU token.
     * @param  int  $id  User ID.
     * @param  string  $verity  Legacy MD5 signature.
     * @param  string  $secret  IYUU secret from config.
     * @return User|null The authenticated user, or null if verification fails.
     */
    public function verifyLegacyIyuu(string $token, int $id, string $verity, string $secret): ?User
    {
        $user = User::query()->findOrFail($id, User::$commonFields);

        try {
            $user->checkIsNormal();
        } catch (\Throwable $e) {
            $this->audit(self::PROVIDER_IYUU, $id, 'user_not_normal', []);

            return null;
        }

        $encryptedResult = md5($token.$id.sha1((string) $user->passkey).$secret);

        if (! hash_equals($encryptedResult, $verity)) {
            $this->audit(self::PROVIDER_IYUU, $id, 'invalid_signature', []);

            return null;
        }

        $this->audit(self::PROVIDER_IYUU, $id, 'success_legacy', []);

        return $user;
    }

    /**
     * Legacy NAS Tools verification (encrypted JSON, backward compatibility).
     *
     * @param  string  $encryptedData  Encrypted JSON payload.
     * @param  string  $key  NAS Tools encryption key.
     * @return User|null The authenticated user, or null if verification fails.
     */
    public function verifyLegacyNasTools(string $encryptedData, string $key): ?User
    {
        try {
            $encrypter = new Encrypter($key);
            $decrypted = $encrypter->decryptString($encryptedData);
            $data = json_decode($decrypted, true);
            if (! is_array($data) || ! isset($data['uid'], $data['passkey'])) {
                $this->audit(self::PROVIDER_NAS_TOOLS, 0, 'invalid_format', []);

                return null;
            }

            $user = User::query()
                ->where('id', (int) $data['uid'])
                ->where('passkey', (string) $data['passkey'])
                ->first();

            if (! $user instanceof User) {
                $this->audit(self::PROVIDER_NAS_TOOLS, (int) $data['uid'], 'user_not_found', []);

                return null;
            }

            $user->checkIsNormal();
        } catch (\Throwable $e) {
            $this->audit(self::PROVIDER_NAS_TOOLS, 0, 'decryption_failed', []);

            return null;
        }

        $this->audit(self::PROVIDER_NAS_TOOLS, (int) $user->id, 'success_legacy', []);

        return $user;
    }

    /**
     * Legacy AMMDS verification (HMAC-SHA256, backward compatibility).
     *
     * @param  Request  $request  Request with uid, timestamp, nonce, signature.
     * @param  string  $secret  AMMDS secret from config.
     * @return User|null The authenticated user, or null if verification fails.
     */
    public function verifyLegacyAmmds(Request $request, string $secret): ?User
    {
        $now = now();
        $timestamp = (int) $request->timestamp;
        $nonce = (string) $request->nonce;
        $uid = (int) $request->uid;
        $signature = (string) $request->signature;

        // Timestamp in milliseconds (legacy AMMDS uses ms)
        if (abs($now->getTimestampMs() - $timestamp) > self::TIMESTAMP_WINDOW * 1000) {
            $this->audit(self::PROVIDER_AMMDS, $uid, 'expired_timestamp', []);

            return null;
        }

        $cacheKey = sprintf('ammdsApprove:%s', $nonce);
        if (Cache::has($cacheKey)) {
            $this->audit(self::PROVIDER_AMMDS, $uid, 'replay_detected', []);

            return null;
        }
        Cache::put($cacheKey, 1, self::NONCE_TTL);

        $user = User::query()->findOrFail($uid, User::$commonFields);

        try {
            $user->checkIsNormal();
        } catch (\Throwable $e) {
            $this->audit(self::PROVIDER_AMMDS, $uid, 'user_not_normal', []);

            return null;
        }

        $passkeyHash = hash('sha256', (string) $user->passkey);
        $dataToSign = sprintf('%s%s%s%s', $user->id, $passkeyHash, $timestamp, $nonce);
        $serverSignature = hash_hmac('sha256', $dataToSign, $secret);

        if (! hash_equals($serverSignature, $signature)) {
            // Note: do NOT log the secret or server signature
            $this->audit(self::PROVIDER_AMMDS, $uid, 'invalid_signature', []);

            return null;
        }

        $this->audit(self::PROVIDER_AMMDS, $uid, 'success_legacy', []);

        return $user;
    }

    /**
     * Write an audit log entry without secrets.
     *
     * @param  string  $provider  Integration provider.
     * @param  int  $userId  User ID (0 if unknown).
     * @param  string  $event  Event type (success, invalid_signature, etc.).
     * @param  array<string, mixed>  $context  Additional context (no secrets).
     */
    public function audit(string $provider, int $userId, string $event, array $context): void
    {
        $context['provider'] = $provider;
        $context['uid'] = $userId;
        $context['ip'] = Network::clientIp();

        // Log without secrets — context is included in the message string
        // since Logger::writeWithContext does not accept a context array.
        $contextStr = '';
        foreach ($context as $key => $value) {
            if (is_string($value) || is_int($value)) {
                $contextStr .= ", $key=$value";
            }
        }

        Logger::writeWithContext(
            (string) sprintf('third_party_auth: %s [%s%s]', $provider, $event, $contextStr),
            (string) 'info',
            (bool) false,
        );
    }

    /**
     * Build the nonce cache key for a provider.
     */
    private function nonceCacheKey(string $provider, string $nonce): string
    {
        return sprintf('third_party_auth:%s:nonce:%s', $provider, hash('sha256', $nonce));
    }
}
