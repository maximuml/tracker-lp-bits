<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on `SafeHtml::fromTrustedHtml()` usage in application code.
 *
 * `LegacyViewSurfaceTest` covers Blade templates under `resources/views`;
 * this test covers the PHP side — where pre-rendered strings are wrapped
 * in a trust marker instead of passing `View`/`Htmlable` objects through
 * the data model. `fromTrustedHtml()` performs NO sanitisation, so every
 * call site is a bypass that must justify itself (sanitised BBCode
 * renderers, framework-generated markup, deliberately assembled HTML).
 *
 * Conversions that remove call sites:
 *  - `fromTrustedHtml(view(...)->render())` → return the `View` object
 *    (it is `Htmlable`; `{{ }}` renders it unescaped) — BUT only when the
 *    value is NOT placed as a top-level element of another `view()`'s
 *    data array: `View::gatherData()` eagerly renders `Renderable`
 *    data values into plain strings, after which `{{ }}`/`@safeHtml`
 *    escapes them. Use `SafeHtml` for values destined for view data;
 *    `View` is safe only as an object property or a top-level return.
 *  - `fromTrustedHtml($plainText)` → `SafeHtml::fromPlainText($plainText)`;
 *  - `fromTrustedHtml($untrustedHtml)` → `SafeHtml::fromUntrustedHtml()`.
 *
 * Baseline captured 2026-10-02 after the trusted-HTML squeeze branch:
 *   - 187 call sites across 67 files (SafeHtml.php's own definition is
 *     excluded). Raised to 121 on 2026-10-27: six View-in-view-data
 *     conversions were reverted after `gatherData` escaping leaks were
 *     identified (technicalInfoResult, clientSelectHtml, migratedHelp,
 *     warnedByHtml, attendance headers, attach-icon). Raised to 117 later
 *     that day: the VERSION constant carries a markup-bearing footer
 *     string (`Powered by <a href=…>`), so its fromPlainText conversion
 *     was reverted after NoLeakedMarkupTest caught the escaped link.
 *
 * The baseline may only be lowered, never raised.
 *
 * To check the current count without running the test:
 *   grep -rn 'fromTrustedHtml(' app --include='*.php' | grep -v 'function fromTrustedHtml' | wc -l
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class TrustedHtmlAppSurfaceTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Baseline: fromTrustedHtml() call sites in app/ PHP code. */
    private const BASELINE_TRUSTED_HTML_CALLS = 117;

    /**
     * Files where fromTrustedHtml is part of the boundary itself and
     * must not be counted: the SafeHtml class owns the trust marker.
     * Paths are relative to app/.
     */
    private const EXEMPT_FILES = [
        'Support/Html/SafeHtml.php',
    ];

    public function test_app_trusted_html_count_does_not_exceed_baseline(): void
    {
        $offenders = $this->countPerFile();

        $total = array_sum($offenders);

        $this->assertLessThanOrEqual(
            self::BASELINE_TRUSTED_HTML_CALLS,
            $total,
            sprintf(
                "fromTrustedHtml() call sites in app/ increased from baseline %d to %d.\n"
               .'fromTrustedHtml() does not sanitise — prefer fromPlainText(), fromUntrustedHtml(), '
               .'or passing View/Htmlable objects to templates instead of render-and-wrap. '
               ."If this increase is intentional, lower the baseline after removing call sites elsewhere.\n\n"
               .'Top offenders: %s',
                self::BASELINE_TRUSTED_HTML_CALLS,
                $total,
                $this->topOffenders($offenders),
            ),
        );
    }

    /**
     * @return array<string, int> file path (relative to app/) => call count
     */
    private function countPerFile(): array
    {
        $counts = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(self::APP_DIR) + 1);
            if (in_array($relative, self::EXEMPT_FILES, true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $count = 0;
            foreach (explode("\n", $content) as $line) {
                if (preg_match('/\bfromTrustedHtml\s*\(/', $line) === 1) {
                    $count++;
                }
            }
            if ($count > 0) {
                $counts[$relative] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $offenders
     */
    private function topOffenders(array $offenders): string
    {
        arsort($offenders);

        $lines = [];
        foreach (array_slice($offenders, 0, 10, true) as $file => $count) {
            $lines[] = sprintf('%s (%d)', $file, $count);
        }

        return implode(', ', $lines);
    }
}
