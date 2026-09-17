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
 * Baselines captured on 2026-09-17 (audit, post-{!! !!}→@safeHtml rename):
 *   - SafeHtml::fromTrustedHtml()  : 550 lines — unverified raw output.
 *     fromTrustedHtml() performs NO sanitisation (it wraps the string in a
 *     marker type, see SafeHtml.php), so
 *
 *     @safeHtml(SafeHtml::fromTrustedHtml($x)) emits the same bytes as
 *     {!! $x !!} — only the spelling changed. Ratcheting it prevents the
 *     "rename the pattern, claim -100%" failure mode: the {!! !!} baseline
 *     above reached 0 while unverified output grew 415 → 550.
 *   - ... over $lang[...] arrays   : 110 — plain text language strings;
 *     these need {{ }}, not a trust marker.
 *   - @safeHtml inside HTML attrs  : 21 — attribute context requires
 *     context-specific escaping; raw HTML in an attribute breaks out of
 *     the quoted context regardless of the trust marker.
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
 *   grep -rn 'fromTrustedHtml' resources/views --include='*.blade.php' | wc -l
 *   grep -rnE 'fromTrustedHtml\(\$lang(_\w+)?\[' resources/views --include='*.blade.php' | wc -l
 *   grep -rniE '(href|src|content|title|alt|value|data-[a-z-]+)="?@safeHtml' resources/views --include='*.blade.php' | wc -l
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LegacyViewSurfaceTest extends TestCase
{
    private const VIEWS_DIR = __DIR__.'/../../resources/views';

    /** Baseline: {!! !!} raw output blocks. */
    private const BASELINE_RAW_OUTPUT = 0;

    /** Baseline: @php directives in views. */
    private const BASELINE_PHP_BLOCKS = 0;

    /** Baseline: \App\Support\Html:: static calls in views. */
    private const BASELINE_HTML_CALLS = 0;

    /**
     * Baseline: <table> elements WITHOUT a data-nx="data" marker.
     * Semantic data tables carry data-nx="data" and stay exempt from the
     * countdown; only unmarked (layout) tables are ratcheted toward zero.
     */
    private const BASELINE_TABLE_TAGS = 0;

    /** Baseline: inline on*= event handler attributes. */
    private const BASELINE_INLINE_HANDLERS = 0;

    /** Baseline: raw <?php open tags inside Blade views (legacy partials). */
    private const BASELINE_RAW_PHP_TAGS = 0;

    /**
     * Baseline: SafeHtml::fromTrustedHtml() calls in views.
     * Unverified raw output — byte-identical to {!! !!}.
     */
    private const BASELINE_TRUSTED_HTML = 432;

    /**
     * Baseline: fromTrustedHtml() over $lang[...] arrays.
     * Language strings are plain text and must use {{ }} instead.
     */
    private const BASELINE_TRUSTED_HTML_LANG = 0;

    /**
     * Baseline: @safeHtml output inside HTML attribute values.
     * Attribute context requires context-specific escaping.
     */
    private const BASELINE_TRUSTED_HTML_ATTR = 0;

    /**
     * Views whose <table> tags are exempt from the layout-table ratchet:
     * semantic data tables (the x-data-table component and similar).
     * Paths are relative to resources/views.
     */
    private const TABLE_EXEMPT_FILES = [
        'components/data-table.blade.php',
    ];

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

    public function test_raw_php_tag_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/<\?php\b/');

        $this->assertLessThanOrEqual(
            self::BASELINE_RAW_PHP_TAGS,
            $count,
            sprintf(
                'raw <?php tag count in views increased from baseline %d to %d. '
               .'Convert the partial to Blade and move logic to controllers/services. '
               .'If this increase is intentional, lower the baseline after removing <?php elsewhere.',
                self::BASELINE_RAW_PHP_TAGS,
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
        $count = $this->countPatternInViews('/<table\b(?![^>]*\bdata-nx=)/i', self::TABLE_EXEMPT_FILES);

        $this->assertLessThanOrEqual(
            self::BASELINE_TABLE_TAGS,
            $count,
            sprintf(
                'unmarked <table> count in views increased from baseline %d to %d. '
               .'Mark semantic data tables with data-nx="data" and replace layout '
               .'tables with CSS grid/flex. '
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

    public function test_trusted_html_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/fromTrustedHtml/');

        $this->assertLessThanOrEqual(
            self::BASELINE_TRUSTED_HTML,
            $count,
            sprintf(
                'fromTrustedHtml() count in views increased from baseline %d to %d. '
               .'fromTrustedHtml() does not sanitise — it is {!! !!} under another name. '
               .'Use {{ }} escaped output or a component that escapes internally. '
               .'If this increase is intentional, lower the baseline after removing fromTrustedHtml elsewhere.',
                self::BASELINE_TRUSTED_HTML,
                $count,
            ),
        );
    }

    public function test_trusted_html_lang_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews('/fromTrustedHtml\(\$lang(_\w+)?\[/');

        $this->assertLessThanOrEqual(
            self::BASELINE_TRUSTED_HTML_LANG,
            $count,
            sprintf(
                'fromTrustedHtml($lang...) count in views increased from baseline %d to %d. '
               .'$lang[...] strings are plain text — wrap them in {{ }} escaped output, '
               .'not in a trust marker. '
               .'If this increase is intentional, lower the baseline after removing fromTrustedHtml elsewhere.',
                self::BASELINE_TRUSTED_HTML_LANG,
                $count,
            ),
        );
    }

    public function test_trusted_html_in_attributes_does_not_exceed_baseline(): void
    {
        $count = $this->countPatternInViews(
            '/(href|src|content|title|alt|value|data-[a-z-]+)\s*=\s*["\x27]?\s*@safeHtml/i',
        );

        $this->assertLessThanOrEqual(
            self::BASELINE_TRUSTED_HTML_ATTR,
            $count,
            sprintf(
                '@safeHtml-in-attribute count in views increased from baseline %d to %d. '
               .'Attribute values need context-specific escaping — raw HTML inside an '
               .'attribute can break out of the quoted context. Escape the value for an '
               .'attribute context instead of marking it trusted. '
               .'If this increase is intentional, lower the baseline after removing it elsewhere.',
                self::BASELINE_TRUSTED_HTML_ATTR,
                $count,
            ),
        );
    }

    /**
     * Count lines matching a pattern across all Blade templates.
     *
     * @param  list<string>  $exemptFiles  paths relative to resources/views to skip
     */
    private function countPatternInViews(string $pattern, array $exemptFiles = []): int
    {
        $count = 0;
        $exempt = array_fill_keys($exemptFiles, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::VIEWS_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ($file->getExtension() !== 'blade.php' && $file->getExtension() !== 'php')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(self::VIEWS_DIR) + 1);
            if (isset($exempt[$relative])) {
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
