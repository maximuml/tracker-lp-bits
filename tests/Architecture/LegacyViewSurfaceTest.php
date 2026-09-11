<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W0-03: Ratchet on the legacy view surface in Blade templates.
 *
 * The project's UI is in the process of being modernised from legacy
 * NexusPHP table-based HTML to Blade Components + Tailwind 4 + Alpine 3.
 * During the transition, legacy patterns must not grow. This test
 * establishes baselines for five categories of legacy surface area and
 * fails if any count increases.
 *
 * Baselines captured on 2026-09-06 (post-T-23, pre-W0):
 *   - {!! !!} raw output          : 415
 *   - @php blocks in views       : 279
 *   - \App\Support\Html:: calls  : 373
 *   - <table> layout tables      : 224
 *   - inline on*= handlers       : 34
 *
 * Each PR that migrates a page to Blade components should reduce one or
 * more of these counts. The baseline constants should be lowered — never
 * raised — as migration progresses.
 *
 * To check current counts without running the test:
 *   grep -rn '{!!' resources/views --include='*.blade.php' | wc -l
 *   grep -rn '@php' resources/views --include='*.blade.php' | wc -l
 *   grep -rn '\\App\\Support\\Html::' resources/views --include='*.blade.php' | wc -l
 *   grep -rn '<table' resources/views --include='*.blade.php' | wc -l
 *   grep -rniE ' (onclick|onchange|onsubmit|onload|onerror|onfocus|onblur|onmouseover|onmouseout|onkeyup|onkeydown|onkeypress)=' resources/views --include='*.blade.php' | wc -l
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LegacyViewSurfaceTest extends TestCase
{
    private const VIEWS_DIR = __DIR__.'/../../resources/views';

    /** Baseline: {!! !!} raw output blocks. */
    private const BASELINE_RAW_OUTPUT = 415;

    /** Baseline: @php directives in views. */
    private const BASELINE_PHP_BLOCKS = 279;

    /** Baseline: \App\Support\Html:: static calls in views. */
    private const BASELINE_HTML_CALLS = 373;

    /** Baseline: <table> elements (layout tables, not data tables). */
    private const BASELINE_TABLE_TAGS = 224;

    /** Baseline: inline on*= event handler attributes. */
    private const BASELINE_INLINE_HANDLERS = 34;

    public function test_raw_output_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/\{!!/');

        $this->assertLessThanOrEqual(
            self::BASELINE_RAW_OUTPUT,
            $count,
            sprintf(
                '{!! !!} count increased from baseline %d to %d. '
               .'Use Blade components or @escaped output instead. '
               .'If this increase is intentional, lower the baseline after removing {!! !!} elsewhere.',
                self::BASELINE_RAW_OUTPUT,
                $count,
            ),
        );
    }

    public function test_php_block_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/@php\b/');

        $this->assertLessThanOrEqual(
            self::BASELINE_PHP_BLOCKS,
            $count,
            sprintf(
                '@php block count increased from baseline %d to %d. '
               .'Move logic to controllers/services and pass data to views. '
               .'If this increase is intentional, lower the baseline after removing @php elsewhere.',
                self::BASELINE_PHP_BLOCKS,
                $count,
            ),
        );
    }

    public function test_html_static_call_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/\\\\App\\\\Support\\\\Html::/');

        $this->assertLessThanOrEqual(
            self::BASELINE_HTML_CALLS,
            $count,
            sprintf(
                '\App\Support\Html:: call count in views increased from baseline %d to %d. '
               .'Replace with Blade components (x-alert, x-table, etc.). '
               .'If this increase is intentional, lower the baseline after removing Html:: calls elsewhere.',
                self::BASELINE_HTML_CALLS,
                $count,
            ),
        );
    }

    public function test_table_tag_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/<table\b/i');

        $this->assertLessThanOrEqual(
            self::BASELINE_TABLE_TAGS,
            $count,
            sprintf(
                '<table> count in views increased from baseline %d to %d. '
               .'Replace layout tables with CSS grid/flex via Tailwind. '
               .'Data tables should use <x-table> component with <thead scope="col">. '
               .'If this increase is intentional, lower the baseline after removing tables elsewhere.',
                self::BASELINE_TABLE_TAGS,
                $count,
            ),
        );
    }

    public function test_inline_event_handler_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews(
            '/\s(onclick|onchange|onsubmit|onload|onerror|onfocus|onblur|onmouseover|onmouseout|onkeyup|onkeydown|onkeypress)\s*=/i',
        );

        $this->assertLessThanOrEqual(
            self::BASELINE_INLINE_HANDLERS,
            $count,
            sprintf(
                'Inline on*= handler count in views increased from baseline %d to %d. '
               .'Use Alpine.js (x-on:click, @click) or unobtrusive JS instead. '
               .'If this increase is intentional, lower the baseline after removing inline handlers elsewhere.',
                self::BASELINE_INLINE_HANDLERS,
                $count,
            ),
        );
    }

    /**
     * Count lines matching a pattern across all Blade templates.
     */
    private function countPatternInViews(string $pattern): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::VIEWS_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ($file->getExtension() !== 'blade.php' && $file->getExtension() !== 'php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                if (preg_match($pattern, $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
