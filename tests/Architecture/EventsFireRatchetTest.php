<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W2-10: Ratchet on Events::fire() calls.
 *
 * Events::fire() is a legacy wrapper that dispatches through
 * ModelEventEnum → event class → Laravel event(). The goal is
 * to replace all Events::fire() calls with direct event() calls,
 * but this requires updating each call site to construct the
 * event class directly.
 *
 * This test ensures no new Events::fire() calls are added.
 *
 * Baseline: 28 calls (captured 2026-09-07).
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class EventsFireRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Baseline: number of Events::fire() calls in app/. */
    private const BASELINE = 28;

    public function test_events_fire_calls_do_not_increase(): void
    {
        $count = $this->countEventsFireCalls();

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $count,
            sprintf(
                'Events::fire() calls in app/ increased from baseline %d to %d. '
               .'Use event() with the event class directly instead of Events::fire(). '
               .'If this increase is intentional, lower the baseline after removing Events::fire() elsewhere.',
                self::BASELINE,
                $count,
            ),
        );
    }

    private function countEventsFireCalls(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                $trimmed = ltrim($line);
                // Skip comments
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '#')
                    || str_starts_with($trimmed, '/*')
                ) {
                    continue;
                }

                if (str_contains($line, 'Events::fire(')) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
