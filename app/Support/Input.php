<?php

declare(strict_types=1);

namespace App\Support;

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
        if (! app()->bound('request')) {
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
        if (! app()->bound('request')) {
            return $default;
        }
        $value = request()->cookie($key);

        return is_string($value) ? $value : $default;
    }

    /**
     * Override a server variable on the current request.
     *
     * Used by legacy controllers that need to fake SCRIPT_NAME for
     * legacy partials that still reference it.
     */
    public static function setServerValue(string $key, string $value): void
    {
        if (app()->bound('request')) {
            request()->server->set($key, $value);
        }
    }

    /**
     * Import `$_REQUEST` keys into the request context.
     *
     * Mirrors `GetVar()`. For a single key, returns the value or `false` when
     * missing. For an array of keys, returns an associative array of found
     * values (so callers can `extract()` them into local scope).
     *
     * @param  string|array<int|string, string>  $name
     */
    public static function getVar(string|array $name): mixed
    {
        if (is_array($name)) {
            $result = [];
            foreach ($name as $var) {
                $value = self::getVar($var);
                if ($value !== false) {
                    $result[$var] = $value;
                }
            }

            return $result;
        }

        $value = request()->input($name);
        if ($value === null) {
            return false;
        }

        app(Globals::class)->set($name, $value);

        return $value;
    }

    /**
     * @param  string|array<int|string, string>  $vars
     * @param  array<string, mixed>  $get
     * @param  array<string, mixed>  $post
     */
    public static function globalize(string|array $vars, array $get, array $post): int
    {
        if (! is_array($vars)) {
            $vars = explode(':', $vars);
        }

        foreach ($vars as $v) {
            if (isset($get[$v])) {
                $value = self::unescape($get[$v]);
                app(Globals::class)->set($v, $value);
            } elseif (isset($post[$v])) {
                $value = self::unescape($post[$v]);
                app(Globals::class)->set($v, $value);
            } else {
                return 0;
            }
        }

        return 1;
    }
}
