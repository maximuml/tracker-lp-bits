<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\CleanupRepository;
use App\Services\Cleanup\Tasks;
use App\Services\CleanupService;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for CleanupService.
 *
 * Covers triggerCron (disabled/enabled), runFull (console path),
 * runAll (forceAll, no-avps, not-due, due), and progress output.
 *
 * The Tasks coordinator and all its task classes are final, so the real
 * container-resolved instance is used — tasks run against the empty test
 * database and return log strings without side effects.
 */
final class CleanupServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('avps')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function service(): CleanupService
    {
        /** @var Tasks $tasks */
        $tasks = $this->app->make(Tasks::class);

        return new CleanupService($tasks);
    }

    private function mockCleanupRepo(): void
    {
        /** @var CleanupRepository&MockInterface $repo */
        $repo = Mockery::mock(CleanupRepository::class);
        $repo->shouldIgnoreMissing();
        $this->app->instance(CleanupRepository::class, $repo);
    }

    private function setGlobal(string $key, mixed $value): void
    {
        app(Globals::class)->set($key, $value);
    }

    // --- triggerCron ---

    public function test_trigger_cron_when_disabled_returns_forbidden(): void
    {
        $this->setGlobal('useCronTriggerCleanUp', false);

        $result = $this->service()->triggerCron();

        $this->assertSame("Forbidden. Clean-up is set to be browser-triggered.\n", $result);
    }

    public function test_trigger_cron_when_enabled_but_not_due_returns_not_triggered(): void
    {
        $this->setGlobal('useCronTriggerCleanUp', true);
        $this->mockCleanupRepo();

        // No avps record → runAll returns false → "Clean-up not triggered."
        $result = $this->service()->triggerCron();

        $this->assertSame("Clean-up not triggered.\n", $result);
    }

    public function test_trigger_cron_when_enabled_and_due_returns_progress(): void
    {
        $this->setGlobal('useCronTriggerCleanUp', true);
        $this->mockCleanupRepo();

        // Insert stale avps records for all 5 cleanup levels so runAll proceeds
        $this->insertStaleAvpsForAllLevels();

        $result = $this->service()->triggerCron();

        $this->assertStringEndsWith("\n", $result);
        $this->assertStringContainsString('Full cleanup is done', $result);
    }

    /**
     * Insert stale avps records for all 5 cleanup priority classes
     * so that runAll(false, false) processes every level.
     */
    private function insertStaleAvpsForAllLevels(): void
    {
        $stale = time() - 2000000;
        foreach (['lastcleantime', 'lastcleantime2', 'lastcleantime3', 'lastcleantime4', 'lastcleantime5'] as $arg) {
            DB::table('avps')->insert([
                'arg' => $arg,
                'value_s' => '',
                'value_u' => $stale,
            ]);
        }
    }

    // --- runFull ---

    public function test_run_full_returns_html_when_in_console(): void
    {
        $this->mockCleanupRepo();

        // PHPUnit runs in console, so the permission check is skipped
        $result = $this->service()->runFull(true, false);

        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('Do Clean-up', $result);
        $this->assertStringContainsString('Done', $result);
    }

    public function test_run_full_with_force_all_omits_force_link(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runFull(true, false);

        $this->assertStringNotContainsString('forceall=1', $result);
    }

    public function test_run_full_without_force_all_includes_force_link(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runFull(false, false);

        $this->assertStringContainsString('forceall=1', $result);
    }

    public function test_run_full_includes_time_consumed(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runFull(true, false);

        $this->assertStringContainsString('Time consumed', $result);
    }

    // --- runAll ---

    public function test_run_all_with_force_all_returns_done_message(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runAll(true, false);

        $this->assertIsString($result);
        $this->assertStringContainsString('Full cleanup is done', $result);
    }

    public function test_run_all_with_force_all_and_print_progress_includes_progress(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runAll(true, true);

        $this->assertIsString($result);
        $this->assertStringContainsString('... done!', $result);
        $this->assertStringContainsString('Full cleanup is done', $result);
    }

    public function test_run_all_without_force_returns_false_when_no_avps(): void
    {
        $this->mockCleanupRepo();

        $result = $this->service()->runAll(false, false);

        $this->assertFalse($result);
        // Should have inserted an avps record
        $this->assertSame(1, DB::table('avps')->where('arg', 'lastcleantime')->count());
    }

    public function test_run_all_without_force_returns_log_when_not_due(): void
    {
        $this->mockCleanupRepo();

        // Insert a recent avps record (timestamp = now, so ts + interval > now)
        DB::table('avps')->insert([
            'arg' => 'lastcleantime',
            'value_s' => '',
            'value_u' => time(),
        ]);

        $result = $this->service()->runAll(false, false);

        $this->assertIsString($result);
        $this->assertStringContainsString('Cleanup ends at Priority Class 0', $result);
    }

    public function test_run_all_without_force_runs_when_due(): void
    {
        $this->mockCleanupRepo();

        // Insert stale avps records for all 5 cleanup levels
        $this->insertStaleAvpsForAllLevels();

        $result = $this->service()->runAll(false, false);

        $this->assertIsString($result);
        $this->assertStringContainsString('Full cleanup is done', $result);
    }

    public function test_run_all_updates_avps_timestamp_when_due(): void
    {
        $this->mockCleanupRepo();

        $oldTime = time() - 999999;
        $this->insertStaleAvpsForAllLevels();

        $this->service()->runAll(false, false);

        $newTime = (int) DB::table('avps')->where('arg', 'lastcleantime')->value('value_u');
        $this->assertGreaterThan($oldTime, $newTime);
    }
}
