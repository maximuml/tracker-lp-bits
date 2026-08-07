<?php

namespace App\Support;

/**
 * Legacy request-input helpers extracted from `include/functions.php`.
 *
 * Backs `mkglobal()` and `unesc()`. These are transitional shims —
 * modern code should use the Request object directly.
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
     * Import the named `$_GET` / `$_POST` keys into `$GLOBALS`.
     *
     * Mirrors `mkglobal()`. Returns `1` on success, `0` if any key
     * is missing.
     *
     * @param  string|array<int|string, string>  $vars
     */
    /**
     * Import `$_REQUEST` keys into `$GLOBALS`.
     *
     * Mirrors `GetVar()`.
     *
     * @param  string|array<int|string, string>  $name
     */
    public static function getVar(string|array $name): mixed
    {
        if (is_array($name)) {
            foreach ($name as $var) {
                self::getVar($var);
            }
            return null;
        }

        $value = SupportContext::getRequestInput($name);
        if ($value === null) {
            return false;
        }

        $GLOBALS[$name] = $value;
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
                $GLOBALS[$v] = $value;
                SupportContext::setGlobal($v, $value);
            } elseif (isset($post[$v])) {
                $value = self::unescape($post[$v]);
                $GLOBALS[$v] = $value;
                SupportContext::setGlobal($v, $value);
            } else {
                return 0;
            }
        }

        return 1;
    }
}
