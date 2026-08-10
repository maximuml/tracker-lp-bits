<?php

declare(strict_types=1);

namespace App\Support\Config;

final class CaptchaConfig extends Config
{
    public function driver(string $default = 'image'): string
    {
        return $this->string('default', $default);
    }

    public function attendanceEnabled(bool $default = true): bool
    {
        $value = $this->data['attendance']['enabled'] ?? $default;
        return $value === true || $value === 'yes' || $value === 1 || $value === '1';
    }

    public function recaptcha(string $key, string $default = ''): string
    {
        $value = $this->data['recaptcha'][$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    public function turnstile(string $key, string $default = ''): string
    {
        $value = $this->data['turnstile'][$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

}
