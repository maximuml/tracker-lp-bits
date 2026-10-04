<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Enums\UserAppendPromotion;
use App\Models\Torrent;
use App\Models\TorrentState;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\ViewModels\Torrent\PromotionBadge;

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
        $token = self::backgroundToken($code);

        return $token === null || $token === '' ? $token : " class='$token'";
    }

    /**
     * Bare `*_bg` class token for a promotion code ('' for code 1,
     * null for unhandled codes) — the typed counterpart of
     * {@see backgroundClass()}.
     */
    private static function backgroundToken(int $code): ?string
    {
        return match ($code) {
            1 => '',
            2 => 'free_bg',
            3 => 'twoup_bg',
            4 => 'twoupfree_bg',
            5 => 'halfdown_bg',
            6 => 'twouphalfdown_bg',
            7 => 'thirtypercentdown_bg',
            default => null,
        };
    }

    /**
     * Bare row CSS class (`free_bg`, …) for a torrent list row, or null
     * when the row gets no class attribute. Typed counterpart of
     * {@see backgroundStyle()}.
     *
     * @param  array<string, mixed>  $torrent
     */
    public static function rowClass(
        int $promotion,
        string $posState,
        array $torrent,
        string $appendPromotion,
    ): ?string {
        $token = self::resolveRowToken($promotion, $posState, $torrent, $appendPromotion);

        return $token === '' ? null : $token;
    }

    /**
     * @param  array<string, mixed>  $torrent
     */
    private static function resolveRowToken(
        int $promotion,
        string $posState,
        array $torrent,
        string $appendPromotion,
    ): ?string {
        $token = null;
        if ($appendPromotion === 'highlight') {
            $globalPromotionState = self::globalSpecialState();
            $token = self::backgroundToken((int) ($globalPromotionState == 1 ? $promotion : $globalPromotionState));
        }

        if ($token === null) {
            $torrentSettings = SiteConfig::current()->torrent->toArray();
            if ($posState === TorrentPosState::STICKY_FIRST->value && ! empty($torrentSettings['sticky_first_level_background_color'])) {
                $token = '';
            } elseif ($posState === TorrentPosState::STICKY_SECOND->value && ! empty($torrentSettings['sticky_second_level_background_color'])) {
                $token = '';
            }
        }

        return $token;
    }

    /**
     * Context-aware wrapper for {@see rowClass()}.
     *
     * @param  array<string, mixed>|null  $torrent
     */
    public static function rowClassWithContext(int $promotion, ?string $posState = '', ?array $torrent = []): ?string
    {
        $user = CurrentUser::instance()->get() ?? [];

        return self::rowClass(
            $promotion,
            (string) ($posState ?? ''),
            $torrent ?? [],
            UserAppendPromotion::tryFrom((int) ($user['appendpromotion'] ?? 2))?->stringValue() ?? 'icon',
        );
    }

    private const PROMOTION_CONFIG = [
        2 => ['class' => 'free', 'text' => 'text_free', 'icon' => 'pro_free', 'alt' => 'Free', 'subColor' => 'nx-promo-end', 'expire' => 'expirefree_torrent'],
        3 => ['class' => 'twoup', 'text' => 'text_two_times_up', 'icon' => 'pro_2up', 'alt' => '2X', 'subColor' => null, 'expire' => 'expiretwoup_torrent'],
        4 => ['class' => 'twoupfree', 'text' => 'text_free_two_times_up', 'icon' => 'pro_free2up', 'alt' => '2X Free', 'subColor' => 'nx-promo-end nx-promo-end--2up', 'expire' => 'expiretwoupfree_torrent'],
        5 => ['class' => 'halfdown', 'text' => 'text_half_down', 'icon' => 'pro_50pctdown', 'alt' => '50%', 'subColor' => null, 'expire' => 'expirehalfleech_torrent'],
        6 => ['class' => 'twouphalfdown', 'text' => 'text_half_down_two_up', 'icon' => 'pro_50pctdown2up', 'alt' => '2X 50%', 'subColor' => null, 'expire' => 'expiretwouphalfleech_torrent'],
        7 => ['class' => 'thirtypercent', 'text' => 'text_thirty_percent_down', 'icon' => 'pro_30pctdown', 'alt' => '30%', 'subColor' => null, 'expire' => 'expirethirtypercentleech_torrent'],
    ];

    /**
     * Build the promotion suffix (word or icon) for a torrent title.
     *
     * Mirrors `get_torrent_promotion_append()`.
     */
    /**
     * @param  array<string, int>  $expires
     */
    public static function append(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        ?string $added,
        int $promotionTimeType,
        ?string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $expires,
    ): string {
        return self::render($promotion, $forceMode, $showTimeLeft, $added, $promotionTimeType, $promotionUntil, $ignoreGlobal, $appendPromotion, $expires, false);
    }

    /**
     * Build the promotion sub-suffix (timeout text) for a torrent title.
     *
     * Mirrors `get_torrent_promotion_append_sub()`.
     */
    /**
     * @param  array<string, int>  $expires
     */
    public static function appendSub(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        ?string $added,
        int $promotionTimeType,
        ?string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $expires,
    ): string {
        return self::render($promotion, $forceMode, $showTimeLeft, $added, $promotionTimeType, $promotionUntil, $ignoreGlobal, $appendPromotion, $expires, true);
    }

    /**
     * Resolve the promotion badge for a torrent row as typed data.
     * Both the badge (word/icon) and its "will end in" suffix derive
     * from the single returned object.
     *
     * @param  array<string, int>  $expires
     */
    public static function badge(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        ?string $added,
        int $promotionTimeType,
        ?string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $expires,
    ): ?PromotionBadge {
        $added = (string) ($added ?? '');
        $promotionUntil = (string) ($promotionUntil ?? '');
        $globalSpState = self::globalSpecialState();
        $log = "[GET_PROMOTION], promotion: $promotion, forcemode: $forceMode, showtimeleft: $showTimeLeft, added: $added, promotionTimeType: $promotionTimeType, promotionUntil: $promotionUntil";
        if ($ignoreGlobal) {
            $globalSpState = 1;
            $log .= ', [IGNORE_GLOBAL]';
        }
        $log .= ', globalSpState == '.$globalSpState;

        $mode = $forceMode !== '' ? $forceMode : $appendPromotion;
        $timeout = null;
        $subColor = null;
        $domttHtml = null;

        if ($globalSpState == 1 && isset(self::PROMOTION_CONFIG[$promotion])) {
            $config = self::PROMOTION_CONFIG[$promotion];
            $expire = (int) ($expires[$config['expire']] ?? 0);
            if ($showTimeLeft && (($expire && $promotionTimeType == 0) || $promotionTimeType == 2)) {
                if ($promotionTimeType == 2) {
                    $futureTime = strtotime($promotionUntil);
                    if ($futureTime === false) {
                        $futureTime = null;
                    }
                } else {
                    $baseTime = strtotime($added);
                    $futureTime = ($baseTime === false ? 0 : $baseTime) + $expire * 86400;
                }
                $timeoutStr = Time::format(date('Y-m-d H:i:s', $futureTime), false, false, true, false, true);
                if ($timeoutStr) {
                    $text = (string) __('legacy/functions.'.$config['text']);
                    $timeout = SafeHtml::fromTrustedHtml((string) $timeoutStr);
                    $subColor = $config['subColor'];
                    $domttHtml = SafeHtml::fromTrustedHtml(trim(view('support._promo-domtt', [
                        'cls' => $config['class'],
                        'text' => $text,
                        'endIn' => (string) __('legacy/functions.text_will_end_in'),
                        'timeout' => SafeHtml::fromTrustedHtml($timeoutStr),
                    ])->render()));
                } else {
                    $promotion = 1;
                }
            }
        }

        $effectiveCode = $globalSpState == 1 ? $promotion : $globalSpState;
        $log .= ", user appendpromotion = $mode";

        $badge = null;
        if (($mode === 'word' || $mode === 'icon') && isset(self::PROMOTION_CONFIG[$effectiveCode])) {
            $config = self::PROMOTION_CONFIG[$effectiveCode];
            $log .= ", promotion or global_sp_state = $effectiveCode";
            $badge = new PromotionBadge(
                mode: $mode,
                cssClass: (string) $config['class'],
                iconClass: (string) $config['icon'],
                alt: (string) $config['alt'],
                text: (string) __('legacy/functions.'.$config['text']),
                timeout: $timeout,
                subColor: $subColor,
                domttHtml: $domttHtml,
            );
        }

        Logger::writeWithContext("$log, badge: ".($badge === null ? '' : "{$badge->mode}:{$badge->cssClass}"));

        return $badge;
    }

    /**
     * Context-aware wrapper for {@see badge()}.
     */
    public static function badgeWithContext(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        ?string $added,
        int $promotionTimeType,
        ?string $promotionUntil,
        bool $ignoreGlobal,
    ): ?PromotionBadge {
        $user = CurrentUser::instance()->get() ?? [];

        return self::badge(
            $promotion,
            $forceMode,
            $showTimeLeft,
            $added,
            $promotionTimeType,
            $promotionUntil,
            $ignoreGlobal,
            UserAppendPromotion::tryFrom((int) ($user['appendpromotion'] ?? 2))?->stringValue() ?? 'icon',
            self::expireTorrentGlobals(),
        );
    }

    /**
     * @param  array<string, int>  $expires
     */
    private static function render(
        int $promotion,
        string $forceMode,
        bool $showTimeLeft,
        ?string $added,
        int $promotionTimeType,
        ?string $promotionUntil,
        bool $ignoreGlobal,
        string $appendPromotion,
        array $expires,
        bool $sub,
    ): string {
        $badge = self::badge(
            $promotion, $forceMode, $showTimeLeft, $added,
            $promotionTimeType, $promotionUntil, $ignoreGlobal,
            $appendPromotion, $expires,
        );
        if ($badge === null) {
            return '';
        }
        if ($sub) {
            if ($badge->timeout === null) {
                return '';
            }
            $endIn = (string) __('legacy/functions.text_will_end_in');

            return $badge->subColor !== null
                ? ltrim(view('support._promo-sub', [
                    'subColor' => $badge->subColor,
                    'endIn' => $endIn,
                    'timeout' => SafeHtml::fromTrustedHtml($badge->timeout->toHtml()),
                ])->render(), "\n")
                : ' '.$endIn.$badge->timeout->toHtml();
        }
        if ($badge->mode === 'word') {
            $tip = $badge->domttHtml !== null ? ' data-domtt-promo' : '';

            return ltrim(view('support._promo-word', [
                'cls' => $badge->cssClass,
                'tip' => SafeHtml::fromTrustedHtml($tip),
                'text' => $badge->text,
                'domtt' => $badge->domttHtml !== null ? SafeHtml::fromTrustedHtml($badge->domttHtml->toHtml()) : null,
            ])->render(), "\n");
        }

        return ltrim(view('support._promo-icon', [
            'cls' => $badge->cssClass,
            'alt' => $badge->alt,
            'text' => $badge->text,
            'domtt' => $badge->domttHtml !== null ? SafeHtml::fromTrustedHtml($badge->domttHtml->toHtml()) : null,
        ])->render(), "\n");
    }

    /**
     * Collect the promotion-expiry globals used by append/appendSub.
     *
     * @return array<string, int>
     */
    private static function expireTorrentGlobals(): array
    {
        $torrent = SiteConfig::current()->torrent;

        return [
            'expirefree_torrent' => $torrent->expireFree(0),
            'expiretwoup_torrent' => $torrent->expireTwoup(0),
            'expiretwoupfree_torrent' => $torrent->expireTwoupfree(0),
            'expirehalfleech_torrent' => $torrent->expireHalfleech(0),
            'expiretwouphalfleech_torrent' => $torrent->expireTwouphalfleech(0),
            'expirethirtypercentleech_torrent' => $torrent->expireThirtypercentleech(0),
        ];
    }

    /**
     * Return the active global special-state promotion code.
     *
     * Mirrors `get_global_sp_state()`.
     */
    /**
     * Set-all global promotion state (`torrents_state.global_sp_state`).
     */
    public static function setGlobalSpecialState(int $state): void
    {
        TorrentState::query()->update(['global_sp_state' => $state]);
    }

    public static function globalSpecialState(): int
    {
        static $state;
        if ($state === null) {
            $timeline = TorrentState::resolveTimeline();
            $current = $timeline['current'] ?? null;

            $state = TorrentPromotion::fromIntSafe(
                is_array($current) && isset($current['global_sp_state'])
                    ? (int) $current['global_sp_state']
                    : TorrentPromotion::NORMAL->value
            )->value;
        }

        return $state;
    }
}
