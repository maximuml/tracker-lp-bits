<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Container;

/**
 * Request-input accessors with type narrowing so callers don't cast
 * mixed request()/cookie()/server() values.
 *
 * Previously `Input` (legacy `mkglobal()`/`unesc()` helpers; both gone).
 */
final class RequestValues
{
    /**
     * Get a server variable as string (or default).
     */
    public static function serverValue(string $key, string $default = ''): string
    {
        if (! Container::getInstance()->bound('request')) {
            return $default;
        }
        $value = request()->server($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Get a cookie value as string|null (or default).
     */
    public static function cookieValue(string $key, ?string $default = null): ?string
    {
        if (! Container::getInstance()->bound('request')) {
            return $default;
        }
        $value = request()->cookie($key);

        return is_string($value) ? $value : $default;
    }
}
