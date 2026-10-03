<?php

namespace Tests\Integration\Support;

use App\Support\Time;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class TimeRenderTest extends TestCase
{
    public function test_format_absolute_time_replaces_spaces_with_br_in_twoline(): void
    {
        // Legacy `str_replace(" ", "<br />", ...)` is global — every
        // space is replaced, not just the date/time separator.
        $this->assertSame('2026-05-18<br /> 04:00:00', Time::formatAbsoluteTime('2026-05-18 04:00:00', true));
        $this->assertSame('a<br /> b<br /> c', Time::formatAbsoluteTime('a b c', true));
    }

    public function test_format_absolute_time_empty_string_round_trips(): void
    {
        $this->assertSame('', Time::formatAbsoluteTime('', false));
        $this->assertSame('', Time::formatAbsoluteTime('', true));
    }

    public function test_format_elapsed_default_wraps_in_span_with_title(): void
    {
        // Default path: no twoline, no oneunit — &nbsp; becomes the
        // localised space separator, the result wraps in <span title>.
        $result = Time::formatElapsedTime(
            '2hour&nbsp;30min',
            '2026-05-18 04:00:00',
            withago: true,
            twoline: false,
            oneunit: false,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="2026-05-18 04:00:00">2hour 30min ago</span>', $result);
    }

    public function test_format_elapsed_omits_textago_when_withago_is_false(): void
    {
        $result = Time::formatElapsedTime(
            '5min',
            '2026-05-18 03:55:00',
            withago: false,
            twoline: false,
            oneunit: false,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="2026-05-18 03:55:00">5min</span>', $result);
    }

    public function test_format_elapsed_twoline_replaces_nbsp_with_br(): void
    {
        $result = Time::formatElapsedTime(
            '2hour&nbsp;30min',
            'T',
            withago: true,
            twoline: true,
            oneunit: false,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="T">2hour<br /> 30min ago</span>', $result);
    }

    public function test_format_elapsed_oneunit_truncates_at_first_nbsp(): void
    {
        // oneunit cuts everything after (and including) the first
        // `&nbsp;` separator, so a "2hour&nbsp;30min" elapsed renders
        // as just "2hour" — even with text_ago appended (since the
        // suffix lands after the truncation point).
        $result = Time::formatElapsedTime(
            '2hour&nbsp;30min',
            'T',
            withago: true,
            twoline: false,
            oneunit: true,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="T">2hour</span>', $result);
    }

    public function test_format_elapsed_oneunit_no_nbsp_returns_verbatim(): void
    {
        // When the elapsed string is already a single unit (no &nbsp;
        // separator) the legacy `if ($length = strpos(...))` is falsy
        // for both "not found" (false) and "found at 0" (0 → falsy),
        // so the value falls through unchanged. Preserved.
        $result = Time::formatElapsedTime(
            '5min',
            'T',
            withago: true,
            twoline: false,
            oneunit: true,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="T">5min ago</span>', $result);
    }

    public function test_format_elapsed_twoline_wins_over_oneunit(): void
    {
        // Branch order matters: if `$twoline` is true the `&nbsp;→<br />`
        // path runs and the oneunit truncation is skipped entirely.
        $result = Time::formatElapsedTime(
            '2hour&nbsp;30min',
            'T',
            withago: false,
            twoline: true,
            oneunit: true,
            textSpace: ' ',
            textAgo: ' ago',
        );
        $this->assertSame('<span title="T">2hour<br /> 30min</span>', $result);
    }

    public function test_format_elapsed_uses_text_space_substitution(): void
    {
        // The localised separator is whatever the caller passes —
        // e.g. an ideographic space, or HTML entity. We just splice
        // it in verbatim.
        $result = Time::formatElapsedTime(
            '2hour&nbsp;30min',
            'T',
            withago: false,
            twoline: false,
            oneunit: false,
            textSpace: '&#12288;',
            textAgo: '',
        );
        $this->assertSame('<span title="T">2hour&#12288;30min</span>', $result);
    }

    public function test_format_elapsed_does_not_escape_title_attribute_legacy_quirk(): void
    {
        // Legacy quirk preserved: the original concatenates `$time`
        // raw into the `title="..."` attribute. We do the same.
        // Existing call sites pass timestamps that never contain
        // quotes, so this hasn't bitten anyone in years.
        $result = Time::formatElapsedTime(
            '5min',
            'a"b',
            withago: false,
            twoline: false,
            oneunit: false,
            textSpace: ' ',
            textAgo: '',
        );
        $this->assertSame('<span title="a"b">5min</span>', $result);
    }
}
