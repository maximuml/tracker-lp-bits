<?php

declare(strict_types=1);

namespace App\Support\Config;

final class AuthorityConfig extends Config
{
    public function permission(string $permission, ?int $default = null): ?int
    {
        $value = $this->data[$permission] ?? $default;

        return $value !== null ? (int) $value : null;
    }

    public function defaultClass(?int $default = null): ?int
    {
        $value = $this->data['defaultclass'] ?? $default;

        return $value !== null ? (int) $value : null;
    }
}
