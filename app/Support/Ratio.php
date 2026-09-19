<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Html\SafeHtml;

/**
 * Stateless helpers for computing and rendering share / seed-leech
 * ratios.
 *
 * Phase 5 of the legacy migration.
 * The legacy procedural helpers
 *
 *   - `get_share_ratio()`
 *   - `get_ratio_color()`
 *   - `get_slr_color()`
 *   - `get_ratio_img()`
 *
 * all collapse into the four static methods below. The legacy
 * functions now proxy here; existing call sites in `public/*.php`,
 * `include/globalfunctions.php`, etc. keep working unmodified.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see PostDiff}.
 */
final class Ratio
{
    /**
     * Compute the upload/download share ratio.
     *
     * Mirrors the legacy `get_share_ratio()` contract exactly:
     *
     *   - returns a `float` truncated to three decimal places when
     *     `$downloaded > 0` (legacy uses `floor(... * 1000) / 1000`,
     *     i.e. truncation, NOT banker's rounding — pinned by tests),
     *   - returns the literal string `"Infinity"` when the user has
     *     uploaded something but never downloaded,
     *   - returns the literal string `"---"` when both are zero.
     *
     * The mixed return type matches what legacy templates already
     * concatenate into HTML — flipping it to a single union would
     * break the `if (is_numeric($ratio))` checks scattered across
     * legacy callers.
     */
    public static function share(int|float $uploaded, int|float $downloaded): float|string
    {
        if ($downloaded) {
            return floor(($uploaded / $downloaded) * 1000) / 1000;
        }

        if ($uploaded) {
            return 'Infinity';
        }

        return '---';
    }

    /**
     * Hex colour for a share ratio (red → dark red as the ratio
     * approaches 1.0, empty string for healthy ≥ 1.0 ratios).
     *
     * Legacy `get_ratio_color()` accepts any value PHP can compare
     * to a float — strings (`"0.5"`), `null`, `false`. We mirror
     * that with `mixed` so we can keep `$row['ratio']` style call
     * sites working without touching them.
     */
    public static function color(mixed $ratio): string
    {
        if ($ratio < 0.1) {
            return '#ff0000';
        }
        if ($ratio < 0.2) {
            return '#ee0000';
        }
        if ($ratio < 0.3) {
            return '#dd0000';
        }
        if ($ratio < 0.4) {
            return '#cc0000';
        }
        if ($ratio < 0.5) {
            return '#bb0000';
        }
        if ($ratio < 0.6) {
            return '#aa0000';
        }
        if ($ratio < 0.7) {
            return '#990000';
        }
        if ($ratio < 0.8) {
            return '#880000';
        }
        if ($ratio < 0.9) {
            return '#770000';
        }
        if ($ratio < 1) {
            return '#660000';
        }

        return '';
    }

    /** Ordered hex ramp of {@see color()} — index+1 = `nx-ratio-*` suffix. */
    private const COLOR_STEPS = [
        '#ff0000', '#ee0000', '#dd0000', '#cc0000', '#bb0000',
        '#aa0000', '#990000', '#880000', '#770000', '#660000',
    ];

    /** Ordered hex ramp of {@see seedLeechColor()} — index+1 = `nx-sl-*` suffix. */
    private const SEED_LEECH_STEPS = [
        '#ff0000', '#ee0000', '#dd0000', '#cc0000', '#bb0000',
        '#aa0000', '#990000', '#880000', '#770000', '#660000',
        '#550000', '#440000', '#330000', '#220000', '#110000',
    ];

    /**
     * CSS utility class (`nx-ratio-1`..`nx-ratio-10`, declared in
     * `public/styles/nexus.css`) matching the {@see color()} bucket —
     * the CSP-safe way to colour a ratio; returns '' when healthy.
     */
    public static function colorClass(mixed $ratio): string
    {
        $index = array_search(self::color($ratio), self::COLOR_STEPS, true);

        return $index === false ? '' : 'nx-ratio-'.($index + 1);
    }

    /**
     * Same as {@see colorClass()} for {@see seedLeechColor()} —
     * emits `nx-sl-1`..`nx-sl-15` or '' when healthy.
     */
    public static function seedLeechColorClass(mixed $ratio): string
    {
        $index = array_search(self::seedLeechColor($ratio), self::SEED_LEECH_STEPS, true);

        return $index === false ? '' : 'nx-sl-'.($index + 1);
    }

    /**
     * Hex colour for a seed-leech-time ratio. Same shape as
     * {@see color()} but the buckets are 10× narrower because the
     * seed/leech ratio sits in a much smaller range in practice
     * (a healthy user is ≥ 0.5, not ≥ 1.0).
     */
    public static function seedLeechColor(mixed $ratio): string
    {
        if ($ratio < 0.025) {
            return '#ff0000';
        }
        if ($ratio < 0.05) {
            return '#ee0000';
        }
        if ($ratio < 0.075) {
            return '#dd0000';
        }
        if ($ratio < 0.1) {
            return '#cc0000';
        }
        if ($ratio < 0.125) {
            return '#bb0000';
        }
        if ($ratio < 0.15) {
            return '#aa0000';
        }
        if ($ratio < 0.175) {
            return '#990000';
        }
        if ($ratio < 0.2) {
            return '#880000';
        }
        if ($ratio < 0.225) {
            return '#770000';
        }
        if ($ratio < 0.25) {
            return '#660000';
        }
        if ($ratio < 0.275) {
            return '#550000';
        }
        if ($ratio < 0.3) {
            return '#440000';
        }
        if ($ratio < 0.325) {
            return '#330000';
        }
        if ($ratio < 0.35) {
            return '#220000';
        }
        if ($ratio < 0.375) {
            return '#110000';
        }

        return '';
    }

    /**
     * `<img>` HTML for a share ratio — picks one of eight smilies
     * from `pic/smilies/*.gif` based on the ratio bucket. Output is
     * a raw HTML fragment because every legacy caller echoes it
     * straight into the page body.
     *
     * Mirrors the legacy `get_ratio_img()` semantics exactly,
     * including the slightly-quirky bucket boundaries (the smiley
     * indexes 163 / 117 / 5 / 3 / 2 / 34 / 10 / 52 are not in
     * monotonic order in the source images — pinning them here so
     * a renumber doesn't silently flip every legacy user-card).
     */
    public static function image(mixed $ratio): SafeHtml
    {
        if ($ratio >= 16) {
            $s = '163';
        } elseif ($ratio >= 8) {
            $s = '117';
        } elseif ($ratio >= 4) {
            $s = '5';
        } elseif ($ratio >= 2) {
            $s = '3';
        } elseif ($ratio >= 1) {
            $s = '2';
        } elseif ($ratio >= 0.5) {
            $s = '34';
        } elseif ($ratio >= 0.25) {
            $s = '10';
        } else {
            $s = '52';
        }

        return SafeHtml::fromTrustedHtml('<img src="pic/smilies/'.$s.'.gif" alt="" />');
    }

    /**
     * Numeric `$uploaded / $downloaded` user-ratio, falling back to
     * `1` when the user has never downloaded. Backs the non-HTML
     * branch of legacy `get_ratio($userid, false)`.
     */
    public static function userRatioNumeric(int|float $uploaded, int|float $downloaded): int|float
    {
        if ($downloaded > 0) {
            return $uploaded / $downloaded;
        }

        return 1;
    }

    /**
     * Bare HTML fragment for the legacy `get_ratio($userid, true)` branch:
     * three-decimal ratio coloured by {@see color()} (or bare when healthy),
     * an "Infinity" variant for upload-only users, and a literal `---`
     * for users who have neither uploaded nor downloaded.
     *
     * Pinned legacy quirks:
     *  - `number_format(ratio, 3)` rounds to three decimals (not the
     *    truncation used by {@see share()}).
     *  - When `color()` returns the empty string (healthy ratio
     *    ≥ 1.0) we skip the colored wrap entirely.
     *  - The `$tooltip` parameter is accepted for API compatibility with
     *    callers but is not emitted; the legacy code never produced a
     *    tooltip wrapper.
     */
    public static function userRatioHtml(
        int|float $uploaded,
        int|float $downloaded,
        string $tooltip,
        string $infinite,
    ): string {
        if ($downloaded > 0) {
            $ratio = $uploaded / $downloaded;
            $color = self::color($ratio);
            $formatted = number_format($ratio, 3);
            if ($color !== '') {
                $formatted = '<span class="'.self::colorClass($ratio).'">'.$formatted.'</span>';
            }

            return $formatted;
        }

        if ($uploaded > 0) {
            return $infinite;
        }

        return '---';
    }

    /**
     * Render the hit-and-run ratio as an HTML fragment.
     *
     * Mirrors `get_hr_ratio()`: computes `$uped / $downed`, wraps the
     * value in the colour from {@see color()}, and caps very large
     * ratios at the literal `Inf.`.
     */
    public static function hr(int|float $uped, int|float $downed): SafeHtml
    {
        if ($downed > 0) {
            $ratio = $uped / $downed;
            $color = self::color($ratio);
            $ratio = $ratio > 10000 ? 'Inf.' : number_format($ratio, 3);
            if ($color) {
                $ratio = '<span class="'.self::colorClass($ratio).'">'.$ratio.'</span>';
            }
        } elseif ($uped > 0) {
            $ratio = 'Inf.';
        } else {
            $ratio = '---';
        }

        return SafeHtml::fromTrustedHtml($ratio);
    }

    /**
     * Leaderboard ratio cell: `number_format($up/$down, $decimals)` wrapped
     * in `<span class="nx-ratio-*">` when {@see color()} flags the ratio — or
     * always wrapped when `$alwaysWrap` (torrent-table legacy quirk emits
     * `<span class="">` for healthy ratios). Caller renders the
     * `$infinite` label when `$down <= 0` (views stay free of division).
     */
    public static function leaderboard(int|float $up, int|float $down, int $decimals = 2, bool $alwaysWrap = false): SafeHtml
    {
        $ratio = $up / $down;
        $color = self::color($ratio);
        $formatted = number_format($ratio, $decimals);
        if ($color !== '' || $alwaysWrap) {
            return SafeHtml::fromTrustedHtml('<span class="'.self::colorClass($ratio).'">'.$formatted.'</span>');
        }

        return SafeHtml::fromTrustedHtml($formatted);
    }

    /**
     * User ratio by id — fetches the user row and renders the numeric
     * or HTML form. Mirrors `get_ratio()`.
     */
    public static function forUserId(int|string $userId, bool $html = true): string|int|float
    {
        $row = UserDisplay::row($userId);
        if (empty($row)) {
            return '---';
        }

        $uped = (float) ($row['uploaded'] ?? 0);
        $downed = (float) ($row['downloaded'] ?? 0);

        if ($html) {
            return self::userRatioHtml($uped, $downed, Locale::trans('label.ratio'), Locale::trans('label.infinite'));
        }

        return self::userRatioNumeric($uped, $downed);
    }
}
