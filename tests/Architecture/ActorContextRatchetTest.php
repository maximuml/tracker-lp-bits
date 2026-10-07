<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * T-20 CI ratchet: the number of `app()` calls and `Globals::class`
 * references in app/ must not grow.
 *
 * This test establishes a baseline count and fails if new occurrences
 * are added. The baseline can be raised when a deliberate migration
 * reduces the count elsewhere — the goal is a monotonically decreasing
 * trend, not a fixed cap.
 *
 * To update the baseline after a legitimate reduction, run:
 *
 *   php tests/Architecture/ActorContextRatchetTest.php --update-baseline
 *
 * and commit the updated constant.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class ActorContextRatchetTest extends TestCase
{
    /**
     * Baseline counts captured at T-20 introduction.
     *
     * These represent the state of the codebase after the first
     * ActorContext migration (ShoutboxService). Future work should
     * reduce these numbers, never increase them.
     *
     * Counts are line-based (grep-style): a file line containing at
     * least one match counts as 1, regardless of how many matches
     * appear on that line.
     *
     * Bumps: +2 for static-only funnels `UserDisplay::userMetaRepository()`
     * and `Promotion::torrentDetailRepository()` — the `self::xRepo()`
     * accessor convention (one `app()` per class, like `X::instance()`);
     * +1 for `ActorContext::userRepo()` — static DTO factory keeps the
     * `self::xRepo()` accessor convention.
     */
    private const BASELINE_APP_CALLS = 64;

    private const APP_DIR = __DIR__.'/../../app';

    public function test_app_call_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPattern('\bapp\s*\(');

        $this->assertLessThanOrEqual(
            self::BASELINE_APP_CALLS,
            $count,
            sprintf(
                'app() call count increased from baseline %d to %d. '
               .'Use constructor injection or ActorContext instead of app(). '
               .'If this increase is intentional and justified, update BASELINE_APP_CALLS.',
                self::BASELINE_APP_CALLS,
                $count,
            ),
        );
    }

    public function test_globals_store_is_not_reintroduced(): void
    {
        $count = $this->countPattern('\bGlobals::|\bGlobals \$|App\\Support\\Globals\b|SettingsSeed');
        $count = $this->countPattern('\bGlobals\b|\bSettingsSeed\b', caseSensitive: true);
        $this->assertSame(
            0,
            $count,
            'The untyped Globals key-value store was removed. Read settings via SiteConfig, '
            .'per-request page state via PageState, and shared view variables via SharedViewVariables.',
        );
    }

    private function countPattern(string $pattern, bool $caseSensitive = false): int
    {
        $count = 0;
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($directory as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            // Count lines containing at least one match (grep-style)
            foreach (explode("\n", $content) as $line) {
                if (preg_match('/'.$pattern.'/'.($caseSensitive ? '' : 'i'), $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
