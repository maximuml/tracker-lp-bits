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

}
