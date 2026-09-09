<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\Attributes\TestCategory;

/**
 * W3-01: Report test classification distribution.
 *
 * Scans all test files for #[TestCategory] attributes and prints
 * a summary of how many test classes fall into each category.
 * Also reports any unclassified test files.
 *
 * Usage:
 *   php artisan test:classify
 */
final class TestClassifyCommand extends Command
{
    protected $signature = 'test:classify';

    protected $description = 'Report test classification distribution (W3-01)';

    public function handle(): int
    {
        $testsDir = base_path('tests');
        $stats = [];
        $unclassified = [];
        $total = 0;

        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testsDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($directory as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $basename = basename($file->getPathname());

            // Skip non-test files.
            if (! preg_match('/class\s+\w+Test\b/', $content)) {
                continue;
            }

            // Skip the ratchet test itself.
            if ($basename === 'TestClassificationRatchetTest.php') {
                continue;
            }

            $total++;

            if (preg_match_all('/#\[TestCategory\s*\(([^]]*)\)\]/', $content, $matches)) {
                foreach ($matches[1] as $args) {
                    preg_match_all('/TestCategory::(\w+)/', $args, $constMatches);
                    foreach ($constMatches[1] as $const) {
                        $value = constant(TestCategory::class.'::'.$const);
                        $stats[$value] = ($stats[$value] ?? 0) + 1;
                    }
                }
            } else {
                $relativePath = str_replace(base_path().'/', '', $file->getPathname());
                $unclassified[] = $relativePath;
            }
        }

        // Print distribution.
        $this->info('Test Classification Distribution');
        $this->line('');
        $this->table(
            ['Category', 'Count'],
            collect(TestCategory::all())
                ->map(fn ($cat) => [$cat, $stats[$cat] ?? 0])
                ->toArray(),
        );

        $this->line('');
        $this->info("Total test classes: {$total}");

        if (! empty($unclassified)) {
            $this->line('');
            $this->warn(sprintf('Unclassified test files (%d):', count($unclassified)));
            foreach ($unclassified as $path) {
                $this->line("  {$path}");
            }

            return self::FAILURE;
        }

        $this->info('All test classes are classified.');

        return self::SUCCESS;
    }
}
