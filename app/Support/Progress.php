<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Legacy progress-bar helper extracted from `include/functions.php`.
 *
 * Backs `get_percent_completed_image()`.
 */
final class Progress
{
    /**
     * Build the HTML progress-bar image for a percentage value.
     *
     * Mirrors `get_percent_completed_image()`. Widths go through the
     * presentational `width` attribute (inline `style` is CSP-blocked).
     */
    public static function percentImage(int|float|string $p): string
    {
        $p = (float) $p;
        $maxpx = 45;

        $progress = '';
        if ($p == 0) {
            $progress = '<img class="progbarrest" src="pic/trans.gif" width="'.$maxpx.'" alt="" />';
        } elseif ($p == 100) {
            $progress = '<img class="progbargreen" src="pic/trans.gif" width="'.$maxpx.'" alt="" />';
        } elseif ($p >= 1 && $p <= 30) {
            $progress = '<img class="progbarred" src="pic/trans.gif" width="'.(int) round($p * ($maxpx / 100)).'" alt="" /><img class="progbarrest" src="pic/trans.gif" width="'.(int) round((100 - $p) * ($maxpx / 100)).'" alt="" />';
        } elseif ($p >= 31 && $p <= 65) {
            $progress = '<img class="progbaryellow" src="pic/trans.gif" width="'.(int) round($p * ($maxpx / 100)).'" alt="" /><img class="progbarrest" src="pic/trans.gif" width="'.(int) round((100 - $p) * ($maxpx / 100)).'" alt="" />';
        } elseif ($p >= 66 && $p <= 99) {
            $progress = '<img class="progbargreen" src="pic/trans.gif" width="'.(int) round($p * ($maxpx / 100)).'" alt="" /><img class="progbarrest" src="pic/trans.gif" width="'.(int) round((100 - $p) * ($maxpx / 100)).'" alt="" />';
        }

        return '<img class="bar_left" src="pic/trans.gif" alt="" />'.$progress.'<img class="bar_right" src="pic/trans.gif" alt="" />';
    }
}
