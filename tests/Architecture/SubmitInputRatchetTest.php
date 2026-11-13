<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Stage-E ratchet: `<input type="submit">` is being phased out in favour of
 * semantic `<button type="submit">` elements (consistent styling hooks,
 * icon/spinner support, better accessibility semantics).
 *
 * The remaining occurrences sit in legacy form views; they are ratcheted
 * here so the count may only shrink as forms migrate.
 *
 * To list files still carrying the pattern:
 *   grep -rln 'type="submit"' resources/views/
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class SubmitInputRatchetTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    /** Baseline: `<input type="submit">` occurrences across Blade views. */
    private const BASELINE_SUBMIT_INPUTS = 73;

    public function test_submit_inputs_do_not_exceed_baseline(): void
    {
        $count = $this->countSubmitInputs(self::BASE_DIR.'/resources/views');

        $this->assertLessThanOrEqual(
            self::BASELINE_SUBMIT_INPUTS,
            $count,
            "`<input type=\"submit\">` occurrences grew to {$count} "
            .'(baseline '.self::BASELINE_SUBMIT_INPUTS.'). New forms must use '
            .'<button type="submit"> — lower the baseline, never raise it.',
        );
    }

    private function countSubmitInputs(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            preg_match_all('/<input\b[^>]*>/i', $content, $tags);
            foreach ($tags[0] as $tag) {
                if (preg_match('/type\s*=\s*["\']submit["\']/i', $tag) === 1) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
