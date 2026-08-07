<?php

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

        $value = SupportContext::getRequestInput($name);
        if ($value === null) {
            return false;
        }

        SupportContext::setGlobal($name, $value);

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
                SupportContext::setGlobal($v, $value);
            } elseif (isset($post[$v])) {
                $value = self::unescape($post[$v]);
                SupportContext::setGlobal($v, $value);
            } else {
                return 0;
            }
        }

        return 1;
    }
}
