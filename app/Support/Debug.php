<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

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
        echo '['.date('Y-m-d H:i:s')."] $line<br />";
        if ($exit) {
            throw new HttpResponseException(new Response(''));
        }
    }

    /**
     * @param  mixed  $vars
     */
    public static function dumpAndExit(...$vars): void
    {
        $html = '<pre>';
        foreach ($vars as $var) {
            $html .= print_r($var, true);
        }
        $html .= '</pre>';
        throw new HttpResponseException(new Response($html));
    }
}
