<?php

namespace App\Support;

/**
 * Stateless formatters extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `mksize()`           (bytes → "1.23 KB"-style)
 *   - `mksize_compact()`   (bytes → "1.23<br />KB"-style)
 *   - `mksize_loose()`     (bytes → "1.23&nbsp;KB"-style)
 *   - `mksizeint()`        (bytes → "1234 kB"-style, integer truncation)
 *   - `mkprettytime()`     (seconds → "d-hh:mm:ss"-style)
 *
 * all collapse into the static methods below. These helpers are
 * echoed all over the legacy UI (uploaded / downloaded / size /
 * elapsed-time columns on torrent / user / forum pages) — there are
 * ~200 call sites across `public/*.php`, so visual parity matters.
 *
 * The four size formatters share the exact same bucket boundaries
 * (1000 × power-of-1024) and rounding (2 digits below TB, 3 from TB
 * up). They differ only in the unit-string separator. The boundaries
 * are not the IEC 1024⁴ powers — they're 1000×1024^n, which makes the
 * "biggest displayable unit" kick in 24% earlier than a "true" base-2
 * cutoff would. That oddity is part of the legacy contract and is
 * pinned by tests; do not "fix" it.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Ratio}, and {@see Validators}.
 */
final class Format
{
    /**
     * Format a byte count as `"<number> <UNIT>"` with a literal
     * space separator. Identical semantics to the legacy
     * `mksize($bytes)`.
     */
    public static function size(int|float $bytes): string
    {
        return self::bytesWithSeparator($bytes, ' ');
    }

    /**
     * Format a byte count as `"<number><br />UNIT"` (the legacy
     * "compact" form used on torrent list pages where the size
     * column is narrow).
     */
    public static function sizeCompact(int|float $bytes): string
    {
        return self::bytesWithSeparator($bytes, '<br />');
    }

    /**
     * Format a byte count as `"<number>&nbsp;UNIT"` (the legacy
     * "loose" form used in HTML attribute contexts where a literal
     * space would word-wrap awkwardly).
     */
    public static function sizeLoose(int|float $bytes): string
    {
        return self::bytesWithSeparator($bytes, '&nbsp;');
    }

    /**
     * Format a byte count as a truncated-integer-with-unit string,
     * with `B` as the smallest unit and a `0` floor on negative
     * inputs. Used in admin-debug contexts where a fractional
     * byte count would be noise.
     *
     * Note the units: this helper uses `kB` (lowercase k) for the
     * 1024-byte bucket, mirroring the legacy. The other three
     * size formatters use `KB`.
     */
    public static function sizeInt(int|float $bytes): string
    {
        $bytes = max(0, $bytes);
        if ($bytes < 1000) {
            return floor($bytes).' B';
        }
        if ($bytes < 1000 * 1024) {
            return floor($bytes / 1024).' kB';
        }
        if ($bytes < 1000 * 1048576) {
            return floor($bytes / 1048576).' MB';
        }
        if ($bytes < 1000 * 1073741824) {
            return floor($bytes / 1073741824).' GB';
        }
        if ($bytes < 1000 * 1099511627776) {
            return floor($bytes / 1099511627776).' TB';
        }

        return floor($bytes / 1125899906842624).' PB';
    }

    /**
     * Inverse of the `size*()` helpers: convert an `($amount, $unit)`
     * pair into a byte count. Mirrors the legacy `getsize_int()`
     * exactly — including the float return type (`floor()` returns
     * `float` in PHP, and existing call sites cast `(int) ...` at the
     * boundary).
     *
     * `$unit` is a single uppercase letter (`B`, `K`, `M`, `G`, `T`,
     * `P`). Unrecognised units fall through to `0.0` (legacy returned
     * `null` from the same branch — `(int) null` is `0`, so the cast
     * site in `take-increment-bulk.php` already collapses that).
     */
    public static function bytesFromUnit(int|float|string $amount, string $unit = 'G'): float
    {
        $amount = (float) $amount;
        $multiplier = match ($unit) {
            'B' => 1,
            'K' => 1024,
            'M' => 1048576,
            'G' => 1073741824,
            'T' => 1099511627776,
            'P' => 1125899906842624,
            default => 0,
        };

        return floor($amount * $multiplier);
    }

    /**
     * Format a duration (in seconds) as a "pretty" string:
     *
     *   - `< 60 s`          → `"m:ss"`        (still uses `m=0`)
     *   - `< 3600 s`        → `"m:ss"`
     *   - `< 86400 s`       → `"h:mm:ss"`
     *   - `>= 86400 s`      → `"<n><day-label>hh:mm:ss"`
     *
     * `$dayLabel` is the language-aware "day(s)" suffix the legacy
     * pulled from `$lang_functions['text_day']`. The proxy in
     * `include/functions.php` reads that global and passes it in;
     * the helper itself stays pure.
     *
     * Negative inputs are clamped to 0 (mirrors the legacy
     * `if ($s < 0) $s = 0;`). Fractional inputs are rounded to the
     * nearest second BEFORE bucketing (so `59.5` becomes 1 minute,
     * not 59 seconds).
     */
    public static function prettyTime(int|float $s, string $dayLabel = 'day(s)'): string
    {
        if ($s < 0) {
            $s = 0;
        }
        $s = (int) round($s);

        $sec = $s % 60;
        $s = (int) floor($s / 60);
        $min = $s % 60;
        $s = (int) floor($s / 60);
        $hour = $s % 24;
        $day = (int) floor($s / 24);

        if ($day) {
            return $day.$dayLabel.sprintf('%02d:%02d:%02d', $hour, $min, $sec);
        }
        if ($hour) {
            return sprintf('%d:%02d:%02d', $hour, $min, $sec);
        }

        return sprintf('%d:%02d', $min, $sec);
    }

    /**
     * Internal: shared bucket-and-format logic for the three
     * fractional size formatters. The bucket boundaries are
     * 1000×1024^n (NOT 1024^(n+1)) — that's a legacy quirk, pinned
     * by tests, do not normalise.
     */
    private static function bytesWithSeparator(int|float $bytes, string $separator): string
    {
        if ($bytes < 1000 * 1024) {
            return number_format($bytes / 1024, 2).$separator.'KB';
        }
        if ($bytes < 1000 * 1048576) {
            return number_format($bytes / 1048576, 2).$separator.'MB';
        }
        if ($bytes < 1000 * 1073741824) {
            return number_format($bytes / 1073741824, 2).$separator.'GB';
        }
        if ($bytes < 1000 * 1099511627776) {
            return number_format($bytes / 1099511627776, 3).$separator.'TB';
        }

        return number_format($bytes / 1125899906842624, 3).$separator.'PB';
    }

    /**
     * Legacy alias for {@see size()}. Backs the legacy `mksize()` helper.
     */
    public static function mksize(int|float $bytes): string
    {
        return self::size($bytes);
    }

    /**
     * Format an elapsed timestamp. Backs the legacy `get_elapsed_time()` helper.
     */
    public static function getElapsedTime(int|string $ts, bool $shortunit = false): string
    {
        $lang_functions = SupportContext::getLangFunctions();

        return \App\Support\Time::elapsedSince((int) $ts, (int) TIMENOW, [
            'year' => $lang_functions['text_year'] ?? '',
            'year_short' => $lang_functions['text_short_year'] ?? '',
            'month' => $lang_functions['text_month'] ?? '',
            'month_short' => $lang_functions['text_short_month'] ?? '',
            'day' => $lang_functions['text_day'] ?? '',
            'day_short' => $lang_functions['text_short_day'] ?? '',
            'hour' => $lang_functions['text_hour'] ?? '',
            'hour_short' => $lang_functions['text_short_hour'] ?? '',
            'min' => $lang_functions['text_min'] ?? '',
            'min_short' => $lang_functions['text_short_min'] ?? '',
            'plural_suffix' => $lang_functions['text_s'] ?? '',
        ], $shortunit);
    }

    /**
     * Return the color code for a ratio. Backs the legacy `get_ratio_color()` helper.
     */
    public static function getRatioColor(int|float $ratio): string
    {
        return \App\Support\Ratio::color((float) $ratio);
    }

    /**
     * Format a comment body. Backs the legacy `format_comment()` helper.
     */
    public static function formatComment(
        string $text,
        bool $stripHtml = true,
        bool $xssclean = false,
        bool $newtab = true,
        bool $imageresizer = true,
        int $imageMaxWidth = 700,
        bool $enableimage = true,
        bool $enableflash = true,
        int $imagenum = -1,
        int $imageMaxHeight = 0,
    ): string {
        return \App\Support\Comment::format(
            $text,
            $stripHtml,
            $xssclean,
            $newtab,
            $imageresizer,
            $imageMaxWidth,
            $enableimage,
            $enableflash,
            $imagenum,
            $imageMaxHeight,
        );
    }

    /**
     * Turn bare URLs into clickable links. Backs the legacy `format_urls()` helper.
     */
    public static function formatUrls(string $text, bool $newWindow = false): string
    {
        return \App\Support\BBCode::formatUrls($text, $newWindow);
    }

    /**
     * Highlight occurrences of a needle in a subject. Backs the legacy `highlight()` helper.
     */
    public static function highlight(string $search, string $subject, string $hlstart = '<b><font class="striking">', string $hlend = '</font></b>'): string
    {
        return \App\Support\Strings::highlight($search, $subject, $hlstart, $hlend);
    }

    /**
     * Locale-aware wrapper for {@see prettyTime()}, mirroring the legacy
     * `mkprettytime()` helper. Reads the "day" label from the current
     * language context.
     */
    public static function prettyTimeWithLocale(int|float $s): string
    {
        $lang_functions = \App\Support\SupportContext::getLangFunctions();

        return self::prettyTime($s, (string) ($lang_functions['text_day'] ?? 'day(s)'));
    }
}
