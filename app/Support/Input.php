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
    public static function globalize(string|array $vars, array $get, array $post): int
    {
        if (! is_array($vars)) {
            $vars = explode(':', $vars);
        }

        foreach ($vars as $v) {
            if (isset($get[$v])) {
                $GLOBALS[$v] = self::unescape($get[$v]);
            } elseif (isset($post[$v])) {
                $GLOBALS[$v] = self::unescape($post[$v]);
            } else {
                return 0;
            }
        }

        return 1;
    }
}
