<?php

declare(strict_types=1);

namespace App\Support\Config;

final class CaptchaConfig extends Config
{
    public function attendanceEnabled(bool $default = true): bool
    {
        $value = $this->data['attendance']['enabled'] ?? $default;

        return $value === true || $value === 'yes' || $value === 1 || $value === '1';
    }
}
