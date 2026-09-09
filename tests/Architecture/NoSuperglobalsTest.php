<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W0-05: Ratchet on direct PHP superglobal access in app/.
 *
 * Direct access to $_SERVER, $_GET, $_POST, $_COOKIE, $_REQUEST, $_SESSION
 * in application code is an Octane-safety risk (superglobals persist across
 * requests under RoadRunner/FrankenPHP) and bypasses Laravel's Request
 * abstraction (TrustProxies, TrimStrings, etc.).
 *
 * This test counts lines containing superglobal references in actual code
 * (not comments or docblocks) and fails if the count increases.
 *
 * Baseline captured on 2026-09-06: 9 lines of actual code usage, all in
 * legitimate wrapper classes:
 *   - DestructiveEnvironmentGuard (2 lines) — reads $_SERVER for test config
 *   - RequestContext (7 lines) — fallback when Request is unavailable
 *
 * To fix a failure: replace $_SERVER/$GET/$POST with $request->server()/
 * $request->input()/etc. via DI.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class NoSuperglobalsTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Baseline: actual code lines referencing superglobals (excluding comments). */
    private const BASELINE = 9;

    public function test_superglobal_access_does_not_exceed_baseline(): void
    {
        $count = $this->countSuperglobalsInCode();

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $count,
            sprintf(
                'Direct superglobal ($_SERVER/$_GET/$_POST/$_COOKIE/$_REQUEST/$_SESSION) '
               .'access in app/ increased from baseline %d to %d. '
               .'Use $request->server()/$request->input() via DI instead. '
               .'If this increase is intentional, lower the baseline after removing superglobal access elsewhere.',
                self::BASELINE,
                $count,
            ),
        );
    }

    /**
     * Count lines with superglobal references in actual code,
     * excluding comment lines and docblocks.
     */
    private function countSuperglobalsInCode(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Skip Install scripts (standalone, IN_NEXUS=true context)
            $relativePath = str_replace(self::APP_DIR.'/', '', $file->getPathname());
            if (str_starts_with($relativePath, 'Support/Install/')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                if (! preg_match('/\$_(GET|POST|SERVER|COOKIE|REQUEST|SESSION)\b/', $line)) {
                    continue;
                }

                // Skip comment lines
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                    || str_starts_with($trimmed, '#')
                    || str_contains($trimmed, '@param')
                    || str_contains($trimmed, '@var')
                ) {
                    continue;
                }

                $count++;
            }
        }

        return $count;
    }
}
