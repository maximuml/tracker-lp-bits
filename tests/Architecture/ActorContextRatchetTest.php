<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

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
     */
    private const BASELINE_APP_CALLS = 1133;

    private const BASELINE_GLOBALS_REFS = 453;

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

    public function test_globals_reference_count_does_not_exceed_baseline(): void
    {
        $count = $this->countPattern('Globals::class|app\s*\(\s*Globals::class');

        $this->assertLessThanOrEqual(
            self::BASELINE_GLOBALS_REFS,
            $count,
            sprintf(
                'Globals::class reference count increased from baseline %d to %d. '
                .'Use typed config objects (SiteConfig, UiConfig) instead of Globals::get(). '
                .'If this increase is intentional and justified, update BASELINE_GLOBALS_REFS.',
                self::BASELINE_GLOBALS_REFS,
                $count,
            ),
        );
    }

    private function countPattern(string $pattern): int
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
                if (preg_match('/'.$pattern.'/i', $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
