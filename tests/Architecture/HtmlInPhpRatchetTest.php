<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on HTML generation inside PHP classes (audit 2026-09-17).
 *
 * LegacyViewSurfaceTest scans only resources/views — so moving HTML from
 * Blade into PHP classes was invisible to the ratchets. That is exactly
 * what happened: layout tables, echo-emitters and ob_start() buffers are
 * now concentrated in app/Services, app/Support and controllers, and the
 * "clean view" metrics reported 0 while the presentation layer just moved.
 *
 * This test establishes line-count baselines over the whole app/ tree and fails
 * if any of them grows. Lower the constants — never raise them — as
 * page-rendering moves to Blade components.
 *
 * Baselines captured on 2026-09-17:
 *   - '<table quoted literals        : 44
 *   - ob_start() calls               : 37
 *   - echo in Services/Controllers   : 204
 *   - lines starting HTML literals   : 1226
 *
 * Exclusions:
 *   - app/Support/Metrics — Prometheus exposition lines, not HTML
 *
 * To check current counts without running the test:
 *   grep -rniE '["\x27]<table' app/ --include='*.php' | grep -v 'Metrics/' | wc -l
 *   grep -rnE '\bob_start\s*\(' app/ --include='*.php' | grep -v 'Metrics/' | wc -l
 *   grep -rnE '\becho\b' app/Services app/Http/Controllers --include='*.php' | wc -l
 *   grep -rnE '["\x27]<[a-zA-Z!/]' app/ --include='*.php' | grep -v 'Metrics/' | wc -l
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class HtmlInPhpRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Subpaths excluded from every metric, relative to app/.
     *
     * @var list<string>
     */
    private const EXCLUDED_SUBPATHS = [
        'Support/Metrics/',
    ];

    /** Baseline: lines with a quoted '<table literal (layout markup in PHP). */
    private const BASELINE_TABLE_LITERALS = 44;

    /** Baseline: ob_start() calls (output buffering = inline page rendering). */
    private const BASELINE_OB_START = 33;

    /** Baseline: lines with `echo` inside app/Services + app/Http/Controllers. */
    private const BASELINE_ECHO_IN_SERVICES = 199;

    /** Baseline: lines where a quoted string starts an HTML tag ('<div', "</td", '<!--'). */
    private const BASELINE_HTML_LITERAL_LINES = 1194;

    public function test_table_literal_count_does_not_exceed_baseline(): void
    {
        $offenders = $this->matchingLines('/["\x27]<table/i');

        $this->assertLessThanOrEqual(
            self::BASELINE_TABLE_LITERALS,
            count($offenders),
            $this->failureMessage(
                'quoted <table literals',
                self::BASELINE_TABLE_LITERALS,
                $offenders,
            ),
        );
    }

    public function test_ob_start_count_does_not_exceed_baseline(): void
    {
        $offenders = $this->matchingLines('/\bob_start\s*\(/');

        $this->assertLessThanOrEqual(
            self::BASELINE_OB_START,
            count($offenders),
            $this->failureMessage(
                'ob_start() calls',
                self::BASELINE_OB_START,
                $offenders,
            ),
        );
    }

    public function test_echo_in_services_count_does_not_exceed_baseline(): void
    {
        $offenders = $this->matchingLines(
            '/\becho\b/',
            [self::APP_DIR.'/Services', self::APP_DIR.'/Http/Controllers'],
        );

        $this->assertLessThanOrEqual(
            self::BASELINE_ECHO_IN_SERVICES,
            count($offenders),
            $this->failureMessage(
                'echo statements in Services/Controllers',
                self::BASELINE_ECHO_IN_SERVICES,
                $offenders,
            ),
        );
    }

    public function test_html_literal_lines_do_not_exceed_baseline(): void
    {
        $offenders = $this->matchingLines('/["\x27]<[a-zA-Z!\/]/');

        $this->assertLessThanOrEqual(
            self::BASELINE_HTML_LITERAL_LINES,
            count($offenders),
            $this->failureMessage(
                'HTML literal lines',
                self::BASELINE_HTML_LITERAL_LINES,
                $offenders,
            ),
        );
    }

    /**
     * Collect matching lines across the app/ tree.
     *
     * @param  list<string>|null  $dirs  restrict to these directories (default: all of app/)
     * @return list<string> file:line entries for the failure message
     */
    private function matchingLines(string $pattern, ?array $dirs = null): array
    {
        $found = [];

        foreach ($dirs ?? [self::APP_DIR] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                $relative = substr($path, strlen(self::APP_DIR) + 1);

                foreach (self::EXCLUDED_SUBPATHS as $excluded) {
                    if (str_starts_with($relative, $excluded)) {
                        continue 2;
                    }
                }

                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                foreach (explode("\n", $content) as $lineNo => $line) {
                    if (preg_match($pattern, $line) === 1) {
                        $found[] = sprintf('%s:%d', $relative, $lineNo + 1);
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $offenders
     */
    private function failureMessage(string $what, int $baseline, array $offenders): string
    {
        $list = count($offenders) > 25
            ? implode("\n  - ", array_slice($offenders, 0, 25)).sprintf("\n  … and %d more", count($offenders) - 25)
            : implode("\n  - ", $offenders);

        return sprintf(
            "%s in app/ increased from baseline %d to %d.\n"
           .'HTML belongs in Blade views/components — the LegacyViewSurfaceTest cannot see '
           .'markup generated inside PHP, so it moved here. Move rendering to a view or '
           ."component, then lower the baseline.\n\nMatches:\n  - %s",
            $what,
            $baseline,
            count($offenders),
            $list,
        );
    }
}
