<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pure promotion (special-state) presentation helpers, drained out of
 * include/functions.php as part of Phase 5.
 *
 * Torrent promotion codes:
 *   1 = none, 2 = free, 3 = 2x up, 4 = 2x up + free,
 *   5 = 50% down, 6 = 2x up + 50% down, 7 = 30% down.
 */
final class Promotion
{
    /**
     * CSS background class for a promotion / global-special-state code,
     * mirroring the duplicated if/elseif chains in get_torrent_bg_color().
     *
     * Returns:
     *   - '' for code 1 (no promotion — caller still treats this as
     *     "handled", i.e. not null, so sticky background is skipped);
     *   - the matching '*_bg' class for codes 2..7;
     *   - null for any other code, so the caller leaves $sphighlight
     *     untouched (preserving the legacy null-vs-empty distinction).
     */
    public static function backgroundClass(int $code): ?string
    {
        return match ($code) {
            1 => '',
            2 => " class='free_bg'",
            3 => " class='twoup_bg'",
            4 => " class='twoupfree_bg'",
            5 => " class='halfdown_bg'",
            6 => " class='twouphalfdown_bg'",
            7 => " class='thirtypercentdown_bg'",
            default => null,
        };
    }
}
