<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Stage-2 ratchet: `<font>` tags are pre-CSS legacy markup.
 *
 * The unified chrome (ADR 0018) renders the header without a single
 * `<font>` tag — colour lives in `modern.css` classes. The remaining
 * occurrences sit in legacy page bodies and PHP HTML builders; they are
 * ratcheted here so the count may only shrink toward zero as page bodies
 * migrate to Blade components.
 *
 * To list files still carrying the tag:
 *   grep -rln '<font' app/ resources/views/
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LegacyFontTagTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    /** Baseline: app/ PHP files containing a '<font' literal. */
    private const BASELINE_APP_FILES = 33;

    /** Baseline: Blade views containing a '<font' literal. */
    private const BASELINE_VIEW_FILES = 37;

    public function test_app_files_with_font_tag_do_not_exceed_baseline(): void
    {
        $files = $this->filesWithFontTag(self::BASE_DIR.'/app');

        $this->assertLessThanOrEqual(
            self::BASELINE_APP_FILES,
            count($files),
            '`<font>`-bearing files in app/ increased to '.count($files)
            .' (baseline '.self::BASELINE_APP_FILES.'). Colour belongs in CSS classes — '
            ."lower the baseline, never raise it.\n".implode("\n", $files),
        );
    }

    public function test_view_files_with_font_tag_do_not_exceed_baseline(): void
    {
        $files = $this->filesWithFontTag(self::BASE_DIR.'/resources/views');

        $this->assertLessThanOrEqual(
            self::BASELINE_VIEW_FILES,
            count($files),
            '`<font>`-bearing views increased to '.count($files)
            .' (baseline '.self::BASELINE_VIEW_FILES.'). Colour belongs in CSS classes — '
            ."lower the baseline, never raise it.\n".implode("\n", $files),
        );
    }

    /**
     * @return list<string>
     */
    private function filesWithFontTag(string $dir): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php')) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content !== false && str_contains($content, '<font')) {
                $found[] = substr($file->getPathname(), strlen(self::BASE_DIR) + 1);
            }
        }

        return $found;
    }
}
