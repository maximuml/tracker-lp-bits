<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use Illuminate\Support\Facades\Cache;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Drives read-only console commands end-to-end so their handle() bodies
 * are executed under coverage (app/Console ratchet, .coverage-baseline.json).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class ReadOnlyCommandsTest extends TestCase
{
    public function test_app_usage_report_to_stdout(): void
    {
        $this->artisan('app:usage-report', ['--top' => 5])
            ->assertExitCode(0);
    }

    public function test_app_usage_report_to_file(): void
    {
        $file = sys_get_temp_dir().'/usage-report-'.uniqid().'.md';

        $this->artisan('app:usage-report', ['--top' => 3, '--output' => $file])
            ->assertExitCode(0);

        $this->assertFileExists($file);
        $this->assertNotSame('', (string) file_get_contents($file));

        unlink($file);
    }

    public function test_route_inventory_formats(): void
    {
        foreach (['md', 'json', 'csv'] as $format) {
            $this->artisan('route:inventory', ['--format' => $format])
                ->assertExitCode(0);
        }
    }

    public function test_route_inventory_to_file(): void
    {
        $file = sys_get_temp_dir().'/routes-'.uniqid().'.json';

        $this->artisan('route:inventory', ['--format' => 'json', '--output' => $file])
            ->assertExitCode(0);

        $this->assertNotEmpty(json_decode((string) file_get_contents($file), true));

        unlink($file);
    }

    public function test_test_classify_runs_clean(): void
    {
        $this->artisan('test:classify')
            ->assertExitCode(0);
    }

    public function test_coverage_ratchet_missing_file_fails(): void
    {
        $this->artisan('coverage:ratchet', [
            'file' => sys_get_temp_dir().'/definitely-missing-'.uniqid().'.xml',
        ])->assertExitCode(1);
    }

    public function test_coverage_ratchet_empty_xml_fails(): void
    {
        $clover = $this->writeClover([]);
        $baseline = $this->writeTempJson([]);

        $this->artisan('coverage:ratchet', [
            'file' => $clover,
            '--baseline' => $baseline,
        ])->assertExitCode(1);

        unlink($clover);
        unlink($baseline);
    }

    public function test_coverage_ratchet_passes_above_threshold(): void
    {
        $clover = $this->writeClover([
            'app/Console/Foo.php' => [['stmt', 1], ['stmt', 1]],
        ]);
        $baseline = $this->writeTempJson([]);

        $this->artisan('coverage:ratchet', [
            'file' => $clover,
            '--baseline' => $baseline,
        ])->assertExitCode(0);

        $written = json_decode((string) file_get_contents($baseline), true);
        $this->assertEquals(100.0, $written['app/Console'] ?? null);

        unlink($clover);
        unlink($baseline);
    }

    public function test_coverage_ratchet_fails_below_threshold(): void
    {
        $clover = $this->writeClover([
            'app/Console/Foo.php' => [['stmt', 0], ['stmt', 0]],
            'app/Unknown/Skip.php' => [['stmt', 1]],
            'vendor/pk/file.php' => [['stmt', 1]],
        ]);
        $baseline = $this->writeTempJson(['app/Console' => 99.9]);

        $this->artisan('coverage:ratchet', [
            'file' => $clover,
            '--baseline' => $baseline,
        ])->assertExitCode(1);

        $written = json_decode((string) file_get_contents($baseline), true);
        $this->assertSame(99.9, $written['app/Console'] ?? null);

        unlink($clover);
        unlink($baseline);
    }

    public function test_settings_validate(): void
    {
        $this->artisan('settings:validate')
            ->assertExitCode(0);
    }

    public function test_settings_validate_unknown_prefix_fails(): void
    {
        $this->artisan('settings:validate', ['--prefix' => 'no_such_prefix'])
            ->assertExitCode(1);
    }

    public function test_delete_expired_token_is_noop_for_far_future(): void
    {
        $this->artisan('user:delete_expired_token', [
            '--uid' => 1,
            '--days' => 999999,
        ])->assertExitCode(0);
    }

    public function test_queue_probe_dispatches_and_exits(): void
    {
        $this->artisan('queue:probe', ['--sleep' => 0])
            ->assertExitCode(0);
    }

    public function test_queue_probe_wait_finds_marker(): void
    {
        // QUEUE_CONNECTION=sync under tests: the job runs inline and its
        // marker is already in Cache before the command polls it.
        $this->artisan('queue:probe', ['--wait' => true, '--timeout' => 5])
            ->assertExitCode(0);
    }

    public function test_queue_probe_wait_times_out_on_unknown_token(): void
    {
        $token = 'missing-'.uniqid();
        Cache::forget("queue:probe:state:{$token}");

        $this->artisan('queue:probe', [
            '--token' => $token,
            '--wait' => true,
            '--timeout' => 1,
        ])->assertExitCode(1);
    }

    /**
     * @param  array<string, list<array{0: string, 1: int}>>  $files
     */
    private function writeClover(array $files): string
    {
        $xml = new \SimpleXMLElement('<coverage><project/></coverage>');
        $project = $xml->project;
        \assert($project !== null);

        foreach ($files as $name => $lines) {
            $file = $project->addChild('file');
            $file->addAttribute('name', $name);
            foreach ($lines as [$type, $count]) {
                $line = $file->addChild('line');
                $line->addAttribute('type', $type);
                $line->addAttribute('count', (string) $count);
            }
        }

        $path = sys_get_temp_dir().'/clover-'.uniqid().'.xml';
        file_put_contents($path, $xml->asXML());

        return $path;
    }

    /**
     * @param  array<string, float>  $data
     */
    private function writeTempJson(array $data): string
    {
        $path = sys_get_temp_dir().'/baseline-'.uniqid().'.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }
}
