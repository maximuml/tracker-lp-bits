<?php

namespace App\Support;

/**
 * Legacy debug helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `printLine()` and `nexus_dd()`. Kept as a single support class
 * because both are developer-only diagnostics.
 */
final class Debug
{
    public static function printLine(string $line, bool $exit = false): void
    {
        echo '[' . date('Y-m-d H:i:s') . "] $line<br />";
        if ($exit) {
            exit(0);
        }
    }

    /**
     * @param  mixed  $vars
     */
    public static function dumpAndExit(...$vars): void
    {
        echo '<pre>';
        foreach ($vars as $var) {
            echo print_r($var, true);
        }
        echo '</pre>';
        exit(0);
    }
}
