<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\SafeHtml;

/**
 * Smiley markup helpers extracted from `include/functions.php`
 * (Phase 5 of the legacy migration).
 *
 * Backs `getSmileIt` / `smile_row`. Every method returns a string;
 * the legacy proxies forwarded the return value already.
 *
 * Lives under `App\Support` because all methods are pure — no DI,
 * no DB, no globals.
 */
final class Smilies
{
    /**
     * Hand-picked smiley indices rendered in the quick reply row,
     * verbatim from the legacy `smile_row()` body. Order matters —
     * existing pages depend on this exact sequence.
     */
    private const QUICK_NUMBERS = [
        4, 5, 39, 25, 11, 8, 10, 15, 27, 57,
        42, 122, 52, 28, 29, 30, 176,
    ];

    public static function link(string $formname, string $taname, int $smilyNumber): SafeHtml
    {
        $tooltipBody = htmlspecialchars(
            "<table><tr><td><img src='pic/smilies/$smilyNumber.gif' alt='' /></td></tr></table>",
            ENT_QUOTES
        );

        return SafeHtml::fromTrustedHtml('<a href="#" data-smile="[em'.$smilyNumber.']"'
            .' data-smile-form="'.htmlspecialchars($formname, ENT_QUOTES).'"'
            .' data-smile-text="'.htmlspecialchars($taname, ENT_QUOTES).'"'
            .' data-domtt-content="'.$tooltipBody.'">'
            .'<img class="nx-smiley" src="pic/smilies/'.$smilyNumber.'.gif" alt="[em'.$smilyNumber.']" /></a>');
    }

    public static function quickRow(string $formname, string $taname): string
    {
        $row = '<div align="center">';
        foreach (self::QUICK_NUMBERS as $smilyNumber) {
            $row .= self::link($formname, $taname, $smilyNumber);
        }
        $row .= '</div>';

        return $row;
    }

    /**
     * Return the web-relative path for a numbered smiley image (e.g.
     * "/pic/smilies/1.gif"), or null when the directory or number is absent.
     *
     * Drained from `get_smile()` in `include/functions.php`. Uses a static
     * cache keyed by the filename stem.
     */
    public static function pathFor(int $number): ?string
    {
        static $paths;
        if ($paths === null) {
            $paths = [];
            $prefix = Path::resolve('public', ROOT_PATH);
            $files = glob(Path::resolve('public/pic/smilies', ROOT_PATH).'/*');
            if ($files !== false) {
                foreach ($files as $value) {
                    $subPath = substr((string) $value, strlen($prefix));
                    $basename = basename($subPath);
                    $key = strstr($basename, '.', true);
                    if ($key !== false) {
                        $paths[$key] = $subPath;
                    }
                }
            }
        }

        return $paths[(string) $number] ?? null;
    }
}
