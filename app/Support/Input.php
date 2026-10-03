<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Legacy request-input helpers extracted from `include/functions.php`.
 *
 * Backs `mkglobal()` and `unesc()`. Values are written into the request
 * context only; no PHP superglobals are mutated.
 */
final class Input
{
    /**
     * Return the value unchanged.
     *
     * Mirrors the legacy `unesc()` no-op.
     */
    public static function unescape(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Get a server variable as string (or default).
     *
     * Wraps request()->server() with type narrowing so callers don't
     * need to cast mixed return values.
     */
    public static function serverValue(string $key, string $default = ''): string
    {
        if (! App::bound('request')) {
            return $default;
        }
        $value = request()->server($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Get a cookie value as string|null (or default).
     *
     * Wraps request()->cookie() with type narrowing so callers don't
     * need to cast mixed return values.
     */
    public static function cookieValue(string $key, ?string $default = null): ?string
    {
        if (! App::bound('request')) {
            return $default;
        }
        $value = request()->cookie($key);

        return is_string($value) ? $value : $default;
    }
}
