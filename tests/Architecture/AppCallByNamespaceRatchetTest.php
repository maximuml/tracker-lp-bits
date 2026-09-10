<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W0-05: Detailed ratchet on app() usage by namespace.
 *
 * ActorContextRatchetTest tracks the total app() count across app/.
 * This test provides per-namespace baselines so that reductions are
 * visible and new app() calls in controllers/services are blocked
 * immediately, not just when the global total exceeds baseline.
 *
 * Baselines captured on 2026-09-07.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class AppCallByNamespaceRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /**
     * Per-namespace baselines for lines containing app().
     *
     * @var array<string, int>
     */
    private const BASELINES = [
        'Controllers' => 255,
        'Services' => 29,
        'Repositories' => 62,
        'Other' => 708,
    ];

    public function test_app_calls_in_controllers_do_not_exceed_baseline(): void
    {
        $count = $this->countAppCallsInDir('Http/Controllers');

        $this->assertLessThanOrEqual(
            self::BASELINES['Controllers'],
            $count,
            sprintf(
                'app() calls in app/Http/Controllers increased from baseline %d to %d. '
               .'Use constructor injection instead of app().',
                self::BASELINES['Controllers'],
                $count,
            ),
        );
    }

    public function test_app_calls_in_services_do_not_exceed_baseline(): void
    {
        $count = $this->countAppCallsInDir('Services');

        $this->assertLessThanOrEqual(
            self::BASELINES['Services'],
            $count,
            sprintf(
                'app() calls in app/Services increased from baseline %d to %d. '
               .'Use constructor injection instead of app().',
                self::BASELINES['Services'],
                $count,
            ),
        );
    }

    public function test_app_calls_in_repositories_do_not_exceed_baseline(): void
    {
        $count = $this->countAppCallsInDir('Repositories');

        $this->assertLessThanOrEqual(
            self::BASELINES['Repositories'],
            $count,
            sprintf(
                'app() calls in app/Repositories increased from baseline %d to %d. '
               .'Use constructor injection instead of app().',
                self::BASELINES['Repositories'],
                $count,
            ),
        );
    }

    public function test_total_app_calls_do_not_exceed_baseline(): void
    {
        $controllers = $this->countAppCallsInDir('Http/Controllers');
        $services = $this->countAppCallsInDir('Services');
        $repositories = $this->countAppCallsInDir('Repositories');
        $total = $this->countAppCallsInDir('');

        $other = $total - $controllers - $services - $repositories;

        $this->assertLessThanOrEqual(
            self::BASELINES['Other'],
            $other,
            sprintf(
                'app() calls in other app/ namespaces increased from baseline %d to %d.',
                self::BASELINES['Other'],
                $other,
            ),
        );
    }

    private function countAppCallsInDir(string $subDir): int
    {
        $dir = self::APP_DIR.($subDir !== '' ? '/'.$subDir : '');
        if (! is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
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
                if (preg_match('/\bapp\s*\(/i', $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
