<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserTimeType;
use App\Support\Html\SafeHtml;
use Carbon\Carbon;

/**
 * Stateless clock / elapsed-time helpers extracted from
 * `include/functions.php`.
 *
 * Phase 5 of the legacy migration.
 * The legacy procedural helpers
 *
 *   - `getmicrotime()`        (current time as a float, seconds since epoch)
 *   - `deadtime()`            (cutoff timestamp for "dead" peer cleanup)
 *   - `get_elapsed_time()`    (localised "5min 30sec" style string)
 *
 * all collapse into static methods below. The proxies in
 * `include/functions.php` thread the language-aware labels from
 * `$lang_functions` and the request-scoped `TIMENOW` constant through
 * to these pure helpers.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Ratio}, {@see Validators}, {@see Format},
 * {@see Strings}.
 */
final class Time
{
    /**
     * Current time in seconds since the Unix epoch, as a float that
     * includes the microsecond fraction.
     *
     * Preserves the legacy `getmicrotime()` arithmetic exactly: parse
     * `microtime()` output, cast both halves to float, sum. This is
     * functionally identical to `microtime(true)` but the legacy form
     * is preserved verbatim to avoid any floating-point divergence
     * for callers comparing timestamps written by old vs new code.
     */
    public static function microtimeFloat(): float
    {
        [$usec, $sec] = explode(' ', microtime());

        return (float) $usec + (float) $sec;
    }

    /**
     * Cutoff timestamp for peer-cleanup: any peer whose
     * `last_action` is older than this is considered dead and gets
     * removed by the autoclean / docleanup jobs.
     *
     * Legacy formula: `time() - floor(announce_interval * 1.3)`. The
     * 30% grace window is preserved exactly. The proxy in
     * `include/functions.php` reads `main.anninterthree` from the
     * settings repository and passes it in.
     *
     * `$now` is injectable for tests; defaults to `time()` so
     * production callers don't have to think about it.
     */
    public static function deadThreshold(int $announceInterval, ?int $now = null): int
    {
        $now ??= time();

        return $now - (int) floor($announceInterval * 1.3);
    }

    /**
     * Whether the weekend upload window is currently open.
     *
     * Extracted from the legacy `get_if_restricted_is_open()`, whose
     * condition relied on PHP operator precedence (`&&` binds tighter
     * than `||`):
     *
     *   $enabled && (w == 0 || (w == 6) && (G >= 12 && G <= 23))
     *
     * which groups as `$enabled && (Sunday || (Saturday && 12..23))`.
     * So, when the `main.sptime` setting is on, the window is open all
     * of Sunday and on Saturday only between 12:00 and 23:00 inclusive.
     * The explicit parentheses below pin that grouping against an
     * accidental re-reading of the original expression.
     *
     * `w` is the numeric day-of-week (0 = Sunday … 6 = Saturday) and
     * `G` the 24-hour hour without leading zeros — both derived from
     * `$timestamp` via `date()`, matching the legacy use of the
     * server-local timezone. `$timestamp` is injectable for tests and
     * defaults to `time()`.
     *
     * The proxy in `include/functions.php` reads the boolean from
     * `Setting::getIsUploadOpenAtWeekend()` and passes it in.
     */
    public static function isWeekendUploadOpen(bool $enabled, ?int $timestamp = null): bool
    {
        $timestamp ??= time();

        $dayOfWeek = (int) date('w', $timestamp);
        $hour = (int) date('G', $timestamp);

        return $enabled && ($dayOfWeek === 0 || ($dayOfWeek === 6 && $hour >= 12));
    }

    /**
     * Localised elapsed-time string ("2year 3month", "5min", etc).
     *
     * Pinned legacy quirks:
     *   - Always uses `abs()` on the delta, so future timestamps
     *     render exactly like past ones (no leading minus, no
     *     "ago" suffix).
     *   - The years and months buckets are *both* computed from
     *     the raw day count, so exact one-year-ago renders as
     *     "1year 0month" (not "1year"). That's the legacy
     *     contract and we keep it.
     *   - The `< 1min` fallback emits the singular label even in
     *     short mode — no plural suffix is appended.
     *   - Pluralisation uses `> 1` (not `>= 2`), so a fractional
     *     `1.5month` renders the plural form. This matches the
     *     legacy `add_s()` and is provided by {@see Strings::pluralize}.
     *
     * `$labels` is the language pack — the proxy in
     * `include/functions.php` reads
     * `$lang_functions['text_{year,month,day,hour,min}']` for the
     * long form, `text_short_{year,month,day,hour,min}` for the
     * short form, and `text_s` for the plural suffix, and passes
     * them in here.
     *
     * @param  array<string, string>  $labels
     */
    public static function elapsedSince(int $ts, int $now, array $labels, bool $shortUnit = false): string
    {
        $mins = (int) floor(abs($now - $ts) / 60);
        $hours = (int) floor($mins / 60);
        $mins -= $hours * 60;
        $days = (int) floor($hours / 24);
        $hours -= $days * 24;
        $months = (int) floor($days / 30);
        $days2 = $days - $months * 30;
        $years = (int) floor($days / 365);
        $months -= $years * 12;

        $pluralSuffix = (string) ($labels['plural_suffix'] ?? '');

        $part = static function (int $n, string $longKey, string $shortKey) use ($labels, $shortUnit, $pluralSuffix): string {
            $long = (string) ($labels[$longKey] ?? '');
            $short = (string) ($labels[$shortKey] ?? '');

            return $n.($shortUnit ? $short : $long.Strings::pluralize($n, '', $pluralSuffix));
        };

        if ($years > 0) {
            return $part($years, 'year', 'year_short').'&nbsp;'.$part($months, 'month', 'month_short');
        }
        if ($months > 0) {
            return $part($months, 'month', 'month_short').'&nbsp;'.$part($days2, 'day', 'day_short');
        }
        if ($days > 0) {
            return $part($days, 'day', 'day_short').'&nbsp;'.$part($hours, 'hour', 'hour_short');
        }
        if ($hours > 0) {
            return $part($hours, 'hour', 'hour_short').'&nbsp;'.$part($mins, 'min', 'min_short');
        }
        if ($mins > 0) {
            return $part($mins, 'min', 'min_short');
        }

        // Sub-minute fallback. The legacy emits "&lt; 1{label}" — note
        // it does NOT append a plural suffix even in long mode.
        return '&lt; 1'.($shortUnit ? (string) ($labels['min_short'] ?? '') : (string) ($labels['min'] ?? ''));
    }

    public static function formatAbsoluteTime(string $time, bool $twoline): string
    {
        if ($twoline) {
            return str_replace(' ', '<br />', $time);
        }

        return $time;
    }

    public static function formatElapsedTime(
        string $elapsed,
        string $time,
        bool $withago,
        bool $twoline,
        bool $oneunit,
        string $textSpace,
        string $textAgo,
    ): string {
        return self::spanTitle($time, self::formatElapsedInner($elapsed, $withago, $twoline, $oneunit, $textSpace, $textAgo));
    }

    private static function spanTitle(string $title, string $inner): string
    {
        return '<span title="'.$title.'">'.$inner.'</span>';
    }

    /**
     * Inner text of {@see formatElapsedTime()} without the `<span>`
     * wrapper — shared with the semantic `<time>` variant.
     */
    private static function formatElapsedInner(
        string $elapsed,
        bool $withago,
        bool $twoline,
        bool $oneunit,
        string $textSpace,
        string $textAgo,
    ): string {
        $newtime = $elapsed.($withago ? $textAgo : '');

        if ($twoline) {
            $newtime = str_replace('&nbsp;', '<br />', $newtime);
        } elseif ($oneunit) {
            // Legacy quirk preserved: original used `if ($length = strpos(...))`
            // which is falsy when the separator is at offset 0 OR absent.
            // We reproduce that with a strict `> 0` check so both shapes
            // (already-single-unit and no-&nbsp;-at-all) fall through to
            // the verbatim value.
            $length = strpos($newtime, '&nbsp;');
            if ($length !== false && $length > 0) {
                $newtime = substr($newtime, 0, $length);
            }
        } else {
            $newtime = str_replace('&nbsp;', $textSpace, $newtime);
        }

        return $newtime;
    }

    /** @return array<string, string> */
    private static function elapsedLabels(): array
    {
        return [
            'year' => (string) (__('legacy/functions.text_year')),
            'year_short' => (string) (__('legacy/functions.text_short_year')),
            'month' => (string) (__('legacy/functions.text_month')),
            'month_short' => (string) (__('legacy/functions.text_short_month')),
            'day' => (string) (__('legacy/functions.text_day')),
            'day_short' => (string) (__('legacy/functions.text_short_day')),
            'hour' => (string) (__('legacy/functions.text_hour')),
            'hour_short' => (string) (__('legacy/functions.text_short_hour')),
            'min' => (string) (__('legacy/functions.text_min')),
            'min_short' => (string) (__('legacy/functions.text_short_min')),
            'plural_suffix' => (string) (__('legacy/functions.text_s')),
        ];
    }

    /**
     * Elapsed/absolute display parts for the legacy (`IN_NEXUS`) branch
     * of {@see format()}, shared with {@see timeParts()}.
     *
     * `mode` distinguishes the two legacy renderings: `absolute` is the
     * raw timestamp text (format()'s historical unwrapped return),
     * `elapsed` is the "N units ago" text that format() wraps in
     * `<span title>`.
     *
     * @return array{mode: 'absolute'|'elapsed', inner: string, title: string}|false|null
     */
    private static function legacyParts(
        mixed $time,
        bool $withago,
        bool $twoline,
        bool $forceago,
        bool $oneunit,
        bool $isfuturetime,
    ): array|false|null {
        $CURUSER = app(CurrentUser::class)->get();
        $TIMENOW = defined('TIMENOW') ? (int) TIMENOW : time();
        $timeStr = $time instanceof Carbon ? $time->toDateTimeString() : (string) $time;

        if (isset($CURUSER) && ($CURUSER['timetype'] ?? 1) != UserTimeType::TIMEALIVE->value && ! $forceago) {
            return [
                'mode' => 'absolute',
                'inner' => self::formatAbsoluteTime($timeStr, $twoline),
                'title' => $timeStr,
            ];
        }

        $timestamp = strtotime($timeStr);
        if ($timestamp === false) {
            return null;
        }

        if ($isfuturetime && $timestamp < $TIMENOW) {
            return false;
        }

        return [
            'mode' => 'elapsed',
            'inner' => self::formatElapsedInner(
                self::elapsedSince((int) $timestamp, $TIMENOW, self::elapsedLabels(), (bool) $oneunit),
                $withago,
                $twoline,
                $oneunit,
                (string) (__('legacy/functions.text_space')),
                (string) (__('legacy/functions.text_ago')),
            ),
            'title' => $timeStr,
        ];
    }

    /**
     * One-stop legacy time formatter.
     *
     * This is a temporary Phase 5 migration shim: it mirrors the
     * `\App\Support\Time::format()` proxy from `include/functions.php`, including the
     * `IN_NEXUS` branch (Carbon diff-for-humans in Laravel context,
     * locale-aware elapsed/absolute time in legacy context) and the
     * `isset($CURUSER)` / `TIMENOW` globals. It will be split into
     * context-appropriate helpers once the legacy bootstrap is gone.
     *
     * The legacy branch returns HTML (`<span title>` for elapsed text,
     * `<br />`-joined absolute text) — only safe inside PHP-built markup,
     * never via `{{ }}`. Views should use {@see timeParts()} (through
     * the `x-time` component) or {@see formatText()}.
     *
     * @return string|false|null
     */
    public static function format(
        mixed $time,
        bool $withago = true,
        bool $twoline = false,
        bool $forceago = false,
        bool $oneunit = false,
        bool $isfuturetime = false,
    ): mixed {
        if (empty($time)) {
            return null;
        }

        if (! (defined('IN_NEXUS') && IN_NEXUS)) {
            try {
                return Carbon::parse($time)->diffForHumans();
            } catch (\Exception $e) {
                if (\function_exists('do_log')) {
                    Logger::writeWithContext($e->getMessage().$e->getTraceAsString(), 'error');
                }

                return $time;
            }
        }

        $parts = self::legacyParts($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
        if ($parts === null || $parts === false) {
            return $parts;
        }
        if ($parts['mode'] === 'absolute') {
            return $parts['inner'];
        }

        return self::spanTitle($parts['title'], $parts['inner']);
    }

    /**
     * Display parts of {@see format()} for the semantic `<time>` element
     * rendered by the `x-time` component — the element markup lives in
     * Blade, this only supplies the escaped-attribute-safe raw values.
     *
     * `inner` is already-safe HTML (elapsed text may contain `<br />`
     * line splits); `datetime`/`title` are raw strings for `{{ }}`
     * attribute escaping.
     *
     * @return array{datetime: string, title: string, inner: SafeHtml}|null
     */
    public static function timeParts(
        mixed $time,
        bool $withago = true,
        bool $twoline = false,
        bool $forceago = false,
        bool $oneunit = false,
        bool $isfuturetime = false,
    ): ?array {
        if (empty($time)) {
            return null;
        }

        if (! (defined('IN_NEXUS') && IN_NEXUS)) {
            $attr = $time instanceof Carbon ? $time->toDateTimeString() : (string) $time;
            try {
                $inner = SafeHtml::fromPlainText(Carbon::parse($time)->diffForHumans());
            } catch (\Exception $e) {
                if (\function_exists('do_log')) {
                    Logger::writeWithContext($e->getMessage().$e->getTraceAsString(), 'error');
                }

                $inner = SafeHtml::fromPlainText($attr);
            }

            return ['datetime' => $attr, 'title' => $attr, 'inner' => $inner];
        }

        $parts = self::legacyParts($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
        if ($parts === null || $parts === false) {
            return null;
        }

        $inner = $parts['mode'] === 'absolute'
            ? self::formatAbsoluteTime(htmlspecialchars($parts['title'], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), $twoline)
            : $parts['inner'];

        return ['datetime' => $parts['title'], 'title' => $parts['title'], 'inner' => SafeHtml::fromTrustedHtml($inner)];
    }

    /**
     * Plain-text variant of {@see format()} for `{{ }}` output and
     * non-HTML data — same display text without the `<span>`/`<br />`
     * markup or `&nbsp;` entities.
     */
    public static function formatText(
        mixed $time,
        bool $withago = true,
        bool $twoline = false,
        bool $forceago = false,
        bool $oneunit = false,
        bool $isfuturetime = false,
    ): ?string {
        $html = self::format($time, $withago, $twoline, $forceago, $oneunit, $isfuturetime);
        if (! is_string($html)) {
            return null;
        }

        return str_replace(
            "\xc2\xa0",
            ' ',
            html_entity_decode(strip_tags((string) preg_replace('#<br\s*/?>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }

    /**
     * Legacy `format_datetime()` helper. Returns `null` for empty input,
     * otherwise parses with Carbon and formats with the supplied pattern.
     * On parse failure the original value is returned (and logged).
     */
    public static function formatDateTime(mixed $datetime, string $format = 'Y-m-d H:i'): ?string
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return Carbon::parse($datetime)->format($format);
        } catch (\Exception) {
            if (\function_exists('do_log')) {
                Logger::writeWithContext("Invalid datetime: $datetime", 'error');
            }

            return (string) $datetime;
        }
    }

    /**
     * Legacy `getDtMillis()` / `getDtMicro()` helpers. Return the current
     * wall-clock time with millisecond / microsecond precision, optionally
     * including the timezone offset.
     */
    public static function millis(bool $withTimeZone = false): string
    {
        $dt = \DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
        if ($dt === false) {
            $dt = new \DateTime;
        }
        $dt->setTimezone(new \DateTimeZone(Env::get('TIMEZONE', 'UTC')));
        $format = $withTimeZone ? 'Y-m-d\\TH:i:s.vP' : 'Y-m-d H:i:s.v';

        return $dt->format($format);
    }

    public static function micro(bool $withTimeZone = false): string
    {
        $dt = \DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
        if ($dt === false) {
            $dt = new \DateTime;
        }
        $dt->setTimezone(new \DateTimeZone(Env::get('TIMEZONE', 'UTC')));
        $format = $withTimeZone ? 'Y-m-d\\TH:i:s.uP' : 'Y-m-d H:i:s.u';

        return $dt->format($format);
    }
}
