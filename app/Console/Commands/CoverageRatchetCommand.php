<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * W3-06: Per-module coverage ratchet.
 *
 * Reads Clover XML coverage files and enforces per-module minimum
 * coverage thresholds. Unlike the overall Unit ≥ 35% gate, this
 * command checks individual app/ subdirectories and fails if any
 * module falls below its configured threshold.
 *
 * Usage:
 *   php artisan coverage:ratchet coverage-unit.xml
 *   php artisan coverage:ratchet coverage-unit.xml --baseline=.coverage-baseline.json
 *
 * The baseline file tracks the best-known coverage per module so
 * coverage can only increase (ratchet up), never decrease.
 */
final class CoverageRatchetCommand extends Command
{
    /** @var string */
    protected $signature = 'coverage:ratchet
        {file : Clover XML coverage file}
        {--baseline=.coverage-baseline.json : Baseline file for ratchet}
        {--report : Write coverage report to coverage-report.json}';

    /** @var string */
    protected $description = 'Enforce per-module coverage thresholds (ratchet)';

    /**
     * Per-module thresholds (minimum line coverage %).
     *
     * These are conservative floors based on current coverage levels.
     * They should be raised as coverage improves.
     */
    private const MODULE_THRESHOLDS = [
        'app/Services' => 30.0,
        'app/Repositories' => 35.0,
        'app/Support' => 40.0,
        'app/Models' => 25.0,
        'app/Http/Controllers' => 20.0,
        'app/Policies' => 30.0,
        'app/Jobs' => 25.0,
        'app/Console' => 30.0,
        'app/Utils' => 40.0,
        'app/ValueObjects' => 50.0,
        'app/DTOs' => 40.0,
        'app/Auth' => 30.0,
        'app/Enums' => 60.0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! file_exists($file)) {
            $this->error("Coverage file not found: {$file}");

            return self::FAILURE;
        }

        $moduleCoverage = $this->parseClover($file);
        if ($moduleCoverage === []) {
            $this->error('No coverage data found in file.');

            return self::FAILURE;
        }

        $baseline = $this->loadBaseline();
        $failures = [];
        $report = [];

        $this->info('Per-module coverage:');
        $this->table(
            ['Module', 'Statements', 'Covered', 'Coverage %', 'Threshold', 'Status'],
            collect($moduleCoverage)->map(function (array $data, string $module) use ($baseline, &$failures, &$report) {
                $pct = $data['statements'] > 0
                    ? ($data['covered'] / $data['statements']) * 100
                    : 0.0;
                $threshold = self::MODULE_THRESHOLDS[$module] ?? 0.0;
                $baselinePct = $baseline[$module] ?? 0.0;
                $effectiveThreshold = max($threshold, $baselinePct);

                $status = 'OK';
                if ($pct < $effectiveThreshold) {
                    $status = 'FAIL';
                    $failures[] = sprintf(
                        '%s: %.1f%% < %.1f%%',
                        $module,
                        $pct,
                        $effectiveThreshold
                    );
                }

                $report[$module] = [
                    'statements' => $data['statements'],
                    'covered' => $data['covered'],
                    'coverage_pct' => round($pct, 2),
                    'threshold' => $effectiveThreshold,
                    'status' => $status,
                ];

                return [
                    $module,
                    $data['statements'],
                    $data['covered'],
                    number_format($pct, 2).'%',
                    number_format($effectiveThreshold, 1).'%',
                    $status,
                ];
            })->toArray()
        );

        if ($this->option('report')) {
            file_put_contents(
                'coverage-report.json',
                json_encode($report, JSON_PRETTY_PRINT)."\n"
            );
            $this->info('Coverage report written to coverage-report.json');
        }

        // Update baseline with current coverage (ratchet up only)
        $this->updateBaseline($baseline, $moduleCoverage);

        if ($failures !== []) {
            $this->error("\nCoverage ratchet failures:");
            foreach ($failures as $f) {
                $this->line("  ✗ {$f}");
            }

            return self::FAILURE;
        }

        $this->info('All modules meet coverage thresholds.');

        return self::SUCCESS;
    }

    /**
     * Parse Clover XML and aggregate coverage per app/ subdirectory.
     *
     * @return array<string, array{statements: int, covered: int}>
     */
    private function parseClover(string $file): array
    {
        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            return [];
        }

        /** @var array<string, array{statements: int, covered: int}> $modules */
        $modules = [];

        $fileNodes = $xml->xpath('//file');
        if (! is_array($fileNodes)) {
            return [];
        }

        foreach ($fileNodes as $fileNode) {
            $filePath = (string) $fileNode['name'];
            if (! str_starts_with($filePath, 'app/')) {
                continue;
            }

            $module = $this->extractModule($filePath);
            if ($module === null) {
                continue;
            }

            if (! isset($modules[$module])) {
                $modules[$module] = ['statements' => 0, 'covered' => 0];
            }

            // Clover <file> has <line type="stmt" count="N"/> elements
            $lineNodes = $fileNode->xpath('.//line[@type="stmt"]');
            if (! is_array($lineNodes)) {
                continue;
            }

            foreach ($lineNodes as $line) {
                $modules[$module]['statements']++;
                if ((int) $line['count'] > 0) {
                    $modules[$module]['covered']++;
                }
            }
        }

        // Sort by module name
        ksort($modules);

        return $modules;
    }

    /**
     * Extract the app/ subdirectory module from a file path.
     */
    private function extractModule(string $path): ?string
    {
        // Match app/Subdir or app/Subdir/Subsubdir (first two levels)
        if (preg_match('#^(app/[^/]+(?:/[^/]+)?)#', $path, $m)) {
            $module = $m[1];
            // Check if this module has a threshold defined
            if (isset(self::MODULE_THRESHOLDS[$module])) {
                return $module;
            }
            // Try first-level only
            if (preg_match('#^(app/[^/]+)#', $path, $m2)) {
                return isset(self::MODULE_THRESHOLDS[$m2[1]]) ? $m2[1] : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function loadBaseline(): array
    {
        $baselineFile = $this->option('baseline');
        if (! is_string($baselineFile) || ! file_exists($baselineFile)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($baselineFile), true);
        if (! is_array($data)) {
            return [];
        }

        /** @var array<string, float> $result */
        $result = [];
        foreach ($data as $module => $pct) {
            if (is_string($module) && (is_float($pct) || is_int($pct))) {
                $result[$module] = (float) $pct;
            }
        }

        return $result;
    }

    /**
     * Update baseline file — ratchet up only (never decrease).
     *
     * @param  array<string, float>  $baseline
     * @param  array<string, array{statements: int, covered: int}>  $moduleCoverage
     */
    private function updateBaseline(array $baseline, array $moduleCoverage): void
    {
        $baselineFile = $this->option('baseline');
        if (! is_string($baselineFile)) {
            return;
        }

        $updated = $baseline;

        foreach ($moduleCoverage as $module => $data) {
            $pct = $data['statements'] > 0
                ? ($data['covered'] / $data['statements']) * 100
                : 0.0;
            $current = $baseline[$module] ?? 0.0;
            // Ratchet up only
            if ($pct > $current) {
                $updated[$module] = round($pct, 2);
            }
        }

        file_put_contents(
            $baselineFile,
            json_encode($updated, JSON_PRETTY_PRINT)."\n"
        );
    }
}
