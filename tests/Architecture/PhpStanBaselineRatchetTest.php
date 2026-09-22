<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * H1: Ratchet on phpstan-baseline-8.neon.
 *
 * The baseline was fully drained (224 -> 0 entries): every ignored error
 * in app/Contracts/Repositories was resolved by real parameter and return
 * types instead. This test blocks the baseline from regrowing — a new
 * suppressed error should be fixed, not re-baselined.
 *
 * Baseline captured on 2026-09-19.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class PhpStanBaselineRatchetTest extends TestCase
{
    private const BASELINE_FILE = __DIR__.'/../../phpstan-baseline-8.neon';

    private const MAX_ALLOWED_ENTRIES = 0;

    public function test_phpstan_baseline_does_not_exceed_baseline(): void
    {
        $this->assertFileExists(self::BASELINE_FILE);

        $contents = file_get_contents(self::BASELINE_FILE);
        $this->assertIsString($contents);

        $entries = substr_count($contents, 'identifier:');

        $this->assertLessThanOrEqual(
            self::MAX_ALLOWED_ENTRIES,
            $entries,
            sprintf(
                'phpstan-baseline-8.neon grew to %d ignored errors. The baseline was fully '
               .'drained on 2026-09-19 — fix the PHPStan finding (add real param/return types) '
               .'instead of re-running --generate-baseline.',
                $entries,
            ),
        );
    }
}
