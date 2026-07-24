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

    /**
     * Build the row background style for a torrent list row.
     *
     * Mirrors `get_torrent_bg_color()`.
     */
    public static function backgroundStyle(
        int $promotion,
        string $posState,
        array $torrent,
        string $appendPromotion,
    ): string {
        $sphighlight = null;
        if ($appendPromotion === 'highlight') {
            $globalPromotionState = \get_global_sp_state();
            $code = ($globalPromotionState == 1) ? $promotion : $globalPromotionState;
            $sphighlight = self::backgroundClass((int) $code);
        }

        if (is_null($sphighlight)) {
            $torrentSettings = \get_setting('torrent');
            if ($posState === \App\Models\Torrent::POS_STATE_STICKY_FIRST && ! empty($torrentSettings['sticky_first_level_background_color'])) {
                $sphighlight = sprintf(' style="background-color: %s"', $torrentSettings['sticky_first_level_background_color']);
            } elseif ($posState === \App\Models\Torrent::POS_STATE_STICKY_SECOND && ! empty($torrentSettings['sticky_second_level_background_color'])) {
                $sphighlight = sprintf(' style="background-color: %s"', $torrentSettings['sticky_second_level_background_color']);
            }
        }

        return (string) \apply_filter('torrent_background_color', (string) $sphighlight, $torrent);
    }
}
