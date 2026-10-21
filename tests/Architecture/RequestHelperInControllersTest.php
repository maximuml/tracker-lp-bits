<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on the global `request()` helper inside app/Http/Controllers.
 *
 * Controllers must take Illuminate\Http\Request (or a FormRequest) via
 * method injection so input sources are explicit (query vs body) and
 * testable. The helper bypasses that contract and made POST handlers read
 * query parameters by accident (see LocationPostRequest regression tests).
 *
 * Baseline captured after the Phase 3.3 sweep: 0 call sites.
 * If this fails: inject the Request into the action (or use
 * `app(Request::class)` for non-action helpers like Controller::respond()).
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RequestHelperInControllersTest extends TestCase
{
    private const CONTROLLERS_DIR = __DIR__.'/../../app/Http/Controllers';

    private const BASELINE = 0;

    public function test_request_helper_calls_do_not_exceed_baseline(): void
    {
        $count = $this->countRequestHelperCalls();

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $count,
            sprintf(
                'request() helper calls in app/Http/Controllers increased from baseline %d to %d. '
               .'Inject Illuminate\Http\Request (or a FormRequest) into the action instead.',
                self::BASELINE,
                $count,
            ),
        );
    }

    private function countRequestHelperCalls(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::CONTROLLERS_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
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
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                ) {
                    continue;
                }

                if (preg_match('/\brequest\(\)/', $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
