<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Html\SafeHtml;
use App\Support\LegacyRuntime;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Legacy-runtime branch of {@see Time::format()},
 * {@see Time::timeParts()} and {@see Time::formatText()}.
 *
 * `LegacyRuntime::isLegacy()` is false in the test environment, so the
 * legacy branch — the code that runs in production via public/index.php —
 * would be invisible to the suite without an explicit `markLegacy()`.
 * That is exactly how the `{{ Time::format() }}` escaped-markup
 * regression shipped unnoticed: nothing ever executed the `<span title>`
 * path.
 *
 * Each test here runs in a separate process with `TIMENOW` defined
 * *before* the application boots, so the legacy branch is exercised for
 * real. `PreserveGlobalState(false)` keeps the parent process's
 * `TIMENOW` constant from leaking in.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TimeLegacyFormatTest extends TestCase
{
    use DatabaseTransactions;

    /** Fixed "now" so elapsed output is deterministic. */
    private const TIMENOW_TS = 1_800_000_000; // 2027-01-15 10:40:00 UTC

    protected function setUp(): void
    {
        if (! defined('TIMENOW')) {
            define('TIMENOW', self::TIMENOW_TS);
        }
        parent::setUp();
        app(LegacyRuntime::class)->bootEntry(true);
    }

    private function legacyTime(): string
    {
        return date('Y-m-d H:i:s', self::TIMENOW_TS - 7200); // 2 hours before TIMENOW
    }

    public function test_format_returns_span_wrapped_elapsed_in_legacy_branch(): void
    {
        $html = Time::format($this->legacyTime(), true, false, true);

        $this->assertIsString($html);
        $this->assertStringStartsWith('<span title="', $html);
        $this->assertStringContainsString('ago', $html);
        $this->assertStringNotContainsString('<time', $html);
    }

    public function test_time_parts_returns_element_data_in_legacy_branch(): void
    {
        $parts = Time::timeParts($this->legacyTime(), true, false, true);

        $this->assertNotNull($parts);
        $this->assertSame($this->legacyTime(), $parts['datetime']);
        $this->assertSame($this->legacyTime(), $parts['title']);
        $this->assertInstanceOf(SafeHtml::class, $parts['inner']);
        $this->assertStringNotContainsString('<span', (string) $parts['inner']);
    }

    public function test_time_parts_matches_format_inner_text(): void
    {
        $span = (string) Time::format($this->legacyTime(), true, false, true);
        $parts = Time::timeParts($this->legacyTime(), true, false, true);

        // Same elapsed display text, different wrapper.
        $spanInner = (string) preg_replace('/^<span title="[^"]*">|<\/span>$/', '', $span);
        $this->assertSame($spanInner, (string) $parts['inner']);
    }

    public function test_format_text_returns_plain_elapsed_in_legacy_branch(): void
    {
        $text = Time::formatText($this->legacyTime(), true, false, true);

        $this->assertIsString($text);
        $this->assertStringContainsString('ago', $text);
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString('&nbsp;', $text);
        $this->assertStringNotContainsString("\xc2\xa0", $text);
    }

    public function test_time_parts_future_time_returns_null_when_flagged(): void
    {
        $past = date('Y-m-d H:i:s', self::TIMENOW_TS - 3600);

        $this->assertNull(Time::timeParts($past, true, false, false, false, true));
        $this->assertNull(Time::timeParts(''));
    }

    public function test_time_parts_twoline_keeps_br_markup(): void
    {
        $parts = Time::timeParts($this->legacyTime(), true, true, true);

        $this->assertNotNull($parts);
        $this->assertStringContainsString('<br />', (string) $parts['inner']);
    }

    public function test_time_absolute_mode_for_default_timetype(): void
    {
        // No CurrentUser -> timetype defaults to absolute display.
        $parts = Time::timeParts($this->legacyTime(), false, false, false);

        $this->assertNotNull($parts);
        $this->assertStringNotContainsString('ago', (string) $parts['inner']);
    }

    public function test_component_view_escapes_attributes_and_renders_inner_markup(): void
    {
        $parts = Time::timeParts($this->legacyTime(), true, false, true);
        $html = view('components.time', ['parts' => $parts])->render();

        $this->assertMatchesRegularExpression(
            '/<time datetime="[^"]*" title="[^"]*">/',
            $html,
        );
        $this->assertStringContainsString('</time>', $html);
        $this->assertStringNotContainsString('&lt;span', $html);
    }

    public function test_component_view_escapes_hostile_datetime(): void
    {
        $html = view('components.time', [
            'parts' => ['datetime' => '"><script>alert(1)</script>', 'title' => 'x', 'inner' => SafeHtml::fromPlainText('x')],
        ])->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;', $html);
    }
}
