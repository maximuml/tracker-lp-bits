<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Torrent;
use App\Models\TorrentState;

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

    private const PROMOTION_CONFIG = [
        2 => ['class' => 'free', 'text' => 'text_free', 'icon' => 'pro_free', 'alt' => 'Free', 'subColor' => '#0000FF', 'expire' => 'expirefree_torrent'],
        3 => ['class' => 'twoup', 'text' => 'text_two_times_up', 'icon' => 'pro_2up', 'alt' => '2X', 'subColor' => null, 'expire' => 'expiretwoup_torrent'],
        4 => ['class' => 'twoupfree', 'text' => 'text_free_two_times_up', 'icon' => 'pro_free2up', 'alt' => '2X Free', 'subColor' => '#00CC66', 'expire' => 'expiretwoupfree_torrent'],
        5 => ['class' => 'halfdown', 'text' => 'text_half_down', 'icon' => 'pro_50pctdown', 'alt' => '50%', 'subColor' => null, 'expire' => 'expirehalfleech_torrent'],
        6 => ['class' => 'twouphalfdown', 'text' => 'text_half_down_two_up', 'icon' => 'pro_50pctdown2up', 'alt' => '2X 50%', 'subColor' => null, 'expire' => 'expiretwouphalfleech_torrent'],
        7 => ['class' => 'thirtypercent', 'text' => 'text_thirty_percent_down', 'icon' => 'pro_30pctdown', 'alt' => '30%', 'subColor' => null, 'expire' => 'expirethirtypercentleech_torrent'],
    ];

    /**
     * Build the promotion suffix (word or icon) for a torrent title.
     *
     * Mirrors `get_torrent_promotion_append()`.
     */
    public static function append(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        string $added,
        int $promotionTimeType,
        string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $labels,
        array $expires,
    ): string {
        return self::render($promotion, $forceMode, $showTimeLeft, $added, $promotionTimeType, $promotionUntil, $ignoreGlobal, $appendPromotion, $labels, $expires, false);
    }

    /**
     * Build the promotion sub-suffix (timeout text) for a torrent title.
     *
     * Mirrors `get_torrent_promotion_append_sub()`.
     */
    public static function appendSub(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        string $added,
        int $promotionTimeType,
        string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $labels,
        array $expires,
    ): string {
        return self::render($promotion, $forceMode, $showTimeLeft, $added, $promotionTimeType, $promotionUntil, $ignoreGlobal, $appendPromotion, $labels, $expires, true);
    }

    private static function render(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        string $added,
        int $promotionTimeType,
        string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $labels,
        array $expires,
        bool $sub,
    ): string {
        $globalSpState = \get_global_sp_state();
        $spTorrent = '';
        $onmouseover = '';
        $log = "[GET_PROMOTION], promotion: $promotion, forcemode: $forceMode, showtimeleft: $showTimeLeft, added: $added, promotionTimeType: $promotionTimeType, promotionUntil: $promotionUntil";
        if ($ignoreGlobal) {
            $globalSpState = 1;
            $log .= ', [IGNORE_GLOBAL]';
        }
        $log .= ', globalSpState == ' . $globalSpState;

        $mode = $forceMode !== '' ? $forceMode : $appendPromotion;

        if ($globalSpState == 1 && isset(self::PROMOTION_CONFIG[$promotion])) {
            $config = self::PROMOTION_CONFIG[$promotion];
            $expire = (int) ($expires[$config['expire']] ?? 0);
            if ($showTimeLeft && (($expire && $promotionTimeType == 0) || $promotionTimeType == 2)) {
                $futureTime = $promotionTimeType == 2 ? strtotime($promotionUntil) : strtotime($added) + $expire * 86400;
                $timeout = \gettime(date('Y-m-d H:i:s', $futureTime), false, false, true, false, true);
                if ($timeout) {
                    $text = $labels[$config['text']] ?? '';
                    if ($sub) {
                        $color = $config['subColor'];
                        $onmouseover = $color
                            ? " <font color=\"$color\">" . ($labels['text_will_end_in'] ?? '') . $timeout . '</font>'
                            : ' ' . ($labels['text_will_end_in'] ?? '') . $timeout;
                    } else {
                        $onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '" . htmlspecialchars("<b><font class=\"{$config['class']}\">$text</font></b>" . ($labels['text_will_end_in'] ?? '') . "<b>$timeout</b>") . "', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
                    }
                } else {
                    $promotion = 1;
                }
            }
        }

        $effectiveCode = $globalSpState == 1 ? $promotion : $globalSpState;
        $log .= ", user appendpromotion = $mode";

        if (($mode === 'word' || $mode === 'icon') && isset(self::PROMOTION_CONFIG[$effectiveCode])) {
            $config = self::PROMOTION_CONFIG[$effectiveCode];
            $log .= ", promotion or global_sp_state = $effectiveCode";
            $text = $labels[$config['text']] ?? '';
            if ($sub) {
                $spTorrent = $onmouseover;
            } elseif ($mode === 'word') {
                $spTorrent = " <b>[<font class='{$config['class']}' $onmouseover>$text</font>]</b>";
            } else {
                $attr = $onmouseover ?: 'title="' . $text . '"';
                $spTorrent = " <img class=\"{$config['icon']}\" src=\"pic/trans.gif\" alt=\"{$config['alt']}\" $attr />";
            }
        }

        \do_log("$log, sp_torrent: $spTorrent");

        return $spTorrent;
    }

    /**
     * Return the active global special-state promotion code.
     *
     * Mirrors `get_global_sp_state()`.
     */
    public static function globalSpecialState(): int
    {
        static $state;
        if ($state === null) {
            $timeline = TorrentState::resolveTimeline();
            $current = $timeline['current'] ?? null;

            $state = is_array($current) && isset($current['global_sp_state'])
                ? (int) $current['global_sp_state']
                : Torrent::PROMOTION_NORMAL;
        }

        return $state;
    }
}
