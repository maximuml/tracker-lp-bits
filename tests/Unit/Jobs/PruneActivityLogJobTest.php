<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\PruneActivityLogJob;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * @covers \App\Jobs\PruneActivityLogJob
 * @covers \App\Console\Commands\PruneActivityLogCommand
 *
 * @group pruning
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PruneActivityLogJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure clean state
        DB::table('activity_log')->delete();
        if (DB::getSchemaBuilder()->hasTable('login_logs')) {
            DB::table('login_logs')->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('iplog')) {
            DB::table('iplog')->delete();
        }
    }

    public function test_job_prunes_old_activity_log_records(): void
    {
        $oldDate = now()->subDays(100)->toDateTimeString();
        $recentDate = now()->subDays(10)->toDateTimeString();

        DB::table('activity_log')->insert([
            ['log_name' => 'test', 'description' => 'old record', 'created_at' => $oldDate, 'updated_at' => $oldDate],
            ['log_name' => 'test', 'description' => 'recent record', 'created_at' => $recentDate, 'updated_at' => $recentDate],
        ]);

        (new PruneActivityLogJob)->handle();

        $this->assertSame(1, DB::table('activity_log')->count());
        $this->assertSame('recent record', DB::table('activity_log')->first()->description);
    }

    public function test_job_does_not_prune_recent_records(): void
    {
        $recentDate = now()->subDays(1)->toDateTimeString();

        DB::table('activity_log')->insert([
            ['log_name' => 'test', 'description' => 'keep me', 'created_at' => $recentDate, 'updated_at' => $recentDate],
        ]);

        (new PruneActivityLogJob)->handle();

        $this->assertSame(1, DB::table('activity_log')->count());
    }

    public function test_job_prunes_old_login_logs(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('login_logs')) {
            $this->markTestSkipped('login_logs table does not exist');
        }

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $oldDate = now()->subDays(200)->toDateTimeString();
        $recentDate = now()->subDays(10)->toDateTimeString();

        DB::table('login_logs')->insert([
            ['uid' => $user1->id, 'ip' => '127.0.0.1', 'created_at' => $oldDate, 'updated_at' => $oldDate],
            ['uid' => $user2->id, 'ip' => '127.0.0.2', 'created_at' => $recentDate, 'updated_at' => $recentDate],
        ]);

        (new PruneActivityLogJob)->handle();

        $this->assertSame(1, DB::table('login_logs')->count());
        $this->assertSame('127.0.0.2', DB::table('login_logs')->first()->ip);
    }

    public function test_command_dry_run_does_not_delete(): void
    {
        $oldDate = now()->subDays(100)->toDateTimeString();

        DB::table('activity_log')->insert([
            ['log_name' => 'test', 'description' => 'old record', 'created_at' => $oldDate, 'updated_at' => $oldDate],
        ]);

        $this->artisan('log:prune', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would delete');

        $this->assertSame(1, DB::table('activity_log')->count());
    }

    public function test_command_prunes_activity_log(): void
    {
        $oldDate = now()->subDays(100)->toDateTimeString();
        $recentDate = now()->subDays(10)->toDateTimeString();

        DB::table('activity_log')->insert([
            ['log_name' => 'test', 'description' => 'old', 'created_at' => $oldDate, 'updated_at' => $oldDate],
            ['log_name' => 'test', 'description' => 'keep', 'created_at' => $recentDate, 'updated_at' => $recentDate],
        ]);

        $this->artisan('log:prune')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('activity_log')->count());
        $this->assertSame('keep', DB::table('activity_log')->first()->description);
    }

    public function test_command_with_custom_days(): void
    {
        $oldDate = now()->subDays(50)->toDateTimeString();
        $recentDate = now()->subDays(10)->toDateTimeString();

        DB::table('activity_log')->insert([
            ['log_name' => 'test', 'description' => '50 days old', 'created_at' => $oldDate, 'updated_at' => $oldDate],
            ['log_name' => 'test', 'description' => '10 days old', 'created_at' => $recentDate, 'updated_at' => $recentDate],
        ]);

        // Default retention is 90 days, so 50-day-old record should survive
        $this->artisan('log:prune', ['--table' => 'activity_log'])
            ->assertSuccessful();

        $this->assertSame(2, DB::table('activity_log')->count());

        // With --days=30, the 50-day-old record should be pruned
        $this->artisan('log:prune', ['--table' => 'activity_log', '--days' => 30])
            ->assertSuccessful();

        $this->assertSame(1, DB::table('activity_log')->count());
        $this->assertSame('10 days old', DB::table('activity_log')->first()->description);
    }

    public function test_command_handles_missing_table(): void
    {
        $this->artisan('log:prune', ['--table' => 'nonexistent_table'])
            ->assertSuccessful()
            ->expectsOutputToContain('does not exist');
    }

    public function test_job_archives_old_login_logs_before_deletion(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('login_logs')) {
            $this->markTestSkipped('login_logs table does not exist');
        }
        if (! DB::getSchemaBuilder()->hasTable('login_logs_archive')) {
            $this->markTestSkipped('login_logs_archive table does not exist');
        }

        $user = User::factory()->create();
        $oldDate = now()->subDays(200)->toDateTimeString();

        DB::table('login_logs')->insert([
            ['uid' => $user->id, 'ip' => '10.0.0.1', 'created_at' => $oldDate, 'updated_at' => $oldDate],
        ]);
        DB::table('login_logs_archive')->delete();

        (new PruneActivityLogJob)->handle();

        // Record deleted from source
        $this->assertSame(0, DB::table('login_logs')->count());
        // Record archived
        $this->assertSame(1, DB::table('login_logs_archive')->count());
        $archived = DB::table('login_logs_archive')->first();
        $this->assertSame('10.0.0.1', $archived->ip);
        $this->assertNotNull($archived->archived_at);
    }

    public function test_job_archives_old_iplog_before_deletion(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('iplog')) {
            $this->markTestSkipped('iplog table does not exist');
        }
        if (! DB::getSchemaBuilder()->hasTable('iplog_archive')) {
            $this->markTestSkipped('iplog_archive table does not exist');
        }

        $user = User::factory()->create();
        $oldDate = now()->subDays(200)->toDateTimeString();

        DB::table('iplog')->insert([
            ['ip' => '10.0.0.2', 'userid' => $user->id, 'access' => $oldDate, 'uri' => '/test', 'count' => 1],
        ]);
        DB::table('iplog_archive')->delete();

        (new PruneActivityLogJob)->handle();

        // Record deleted from source
        $this->assertSame(0, DB::table('iplog')->where('userid', $user->id)->count());
        // Record archived
        $this->assertSame(1, DB::table('iplog_archive')->where('userid', $user->id)->count());
        $archived = DB::table('iplog_archive')->where('userid', $user->id)->first();
        $this->assertSame('10.0.0.2', $archived->ip);
        $this->assertSame('/test', $archived->uri);
    }

    protected function tearDown(): void
    {
        DB::table('activity_log')->delete();
        if (DB::getSchemaBuilder()->hasTable('login_logs')) {
            DB::table('login_logs')->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('login_logs_archive')) {
            DB::table('login_logs_archive')->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('iplog_archive')) {
            DB::table('iplog_archive')->delete();
        }

        parent::tearDown();
    }
}
