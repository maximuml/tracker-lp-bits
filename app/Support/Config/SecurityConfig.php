<?php

declare(strict_types=1);

namespace App\Support\Config;

final class SecurityConfig extends Config
{
    public function cheaterdet(int $default = 0): int
    {
        return $this->int('cheaterdet', $default);
    }

    public function noDetect(int $default = 0): int
    {
        return $this->int('nodetect', $default);
    }

    public function secureLogin(bool $default = true): bool
    {
        return $this->bool('securelogin', $default);
    }

    public function loginSecretDeadline(?string $default = null): ?string
    {
        $value = $this->data['login_secret_deadline'] ?? $default;

        return $value !== null ? (string) $value : null;
    }

    public function maxIp(int $default = 0): int
    {
        return $this->int('maxip', $default);
    }

    public function maxLoginAttempts(int $default = 10): int
    {
        return $this->int('maxloginattempts', $default);
    }

    public function captchaRequired(bool $default = false): bool
    {
        return $this->bool('iv', $default);
    }

    public function guestVisitType(): ?string
    {
        $value = $this->data['guest_visit_type'] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public function guestVisitValue(string $type): ?string
    {
        $key = "guest_visit_value_{$type}";
        $value = $this->data[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public function loginType(): ?string
    {
        $value = $this->data['login_type'] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public function loginSecret(string $default = ''): string
    {
        return $this->string('login_secret', $default);
    }

    public function httpsAnnounceUrl(string $default = ''): string
    {
        return $this->string('https_announce_url', $default);
    }

    /**
     * Comma-separated list of blacklisted port ranges/singles, e.g. "411-413,6881-6889,1214".
     * Empty string means no blacklist.
     */
    public function portBlacklist(string $default = '411-413,6881-6889,1214,6346-6347,4662,6699'): string
    {
        return $this->string('port_blacklist', $default);
    }

    /**
     * Passkey login v2 feature flag.
     * When true, the v2 protocol is used at /auth/passkey.
     * When false, the legacy protocol is used (if configured).
     */
    public function passkeyLoginV2Enabled(bool $default = false): bool
    {
        return $this->bool('passkey_login_v2_enabled', $default);
    }

    /**
     * Current signing key for passkey login v2.
     * Generated from bin2hex(random_bytes(32)) — 64-char hex string.
     */
    public function passkeyLoginSigningKeyCurrent(string $default = ''): string
    {
        return $this->string('passkey_login_signing_key_current', $default);
    }

    /**
     * Current signing key ID for passkey login v2.
     * Used by clients to select the correct key during rotation.
     */
    public function passkeyLoginSigningKeyIdCurrent(string $default = ''): string
    {
        return $this->string('passkey_login_signing_key_id_current', $default);
    }

    /**
     * Previous signing key for passkey login v2 (for rotation overlap).
     * Accepted alongside the current key during the overlap window.
     */
    public function passkeyLoginSigningKeyPrevious(string $default = ''): string
    {
        return $this->string('passkey_login_signing_key_previous', $default);
    }

    /**
     * Previous signing key ID for passkey login v2.
     */
    public function passkeyLoginSigningKeyIdPrevious(string $default = ''): string
    {
        return $this->string('passkey_login_signing_key_id_previous', $default);
    }
}
