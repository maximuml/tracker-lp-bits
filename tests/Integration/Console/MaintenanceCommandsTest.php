<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use App\Models\User;
use App\Support\PasswordHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Drives maintenance commands without a dedicated test so their handle()
 * bodies run under coverage (app/Console ratchet, .coverage-baseline.json).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class MaintenanceCommandsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_password_report_table_lists_md5_user(): void
    {
        $md5 = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);

        $command = $this->artisan('users:legacy-password-report');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Legacy password hash report')
            ->expectsOutputToContain((string) $md5->username)
            ->assertExitCode(0);
    }

    public function test_legacy_password_report_json_filters_by_algo(): void
    {
        $md5 = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);
        $sha = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_SHA256]);

        $exit = Artisan::call('users:legacy-password-report', [
            '--format' => 'json',
            '--algo' => PasswordHasher::ALGO_SHA256,
        ]);
        $this->assertSame(0, $exit);

        /** @var array{users: list<array{0: int}>} $report */
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_column($report['users'], 0);
        $this->assertContains($sha->id, $ids);
        $this->assertNotContains($md5->id, $ids);
    }

    public function test_legacy_password_report_days_filter_skips_recent_logins(): void
    {
        $recent = User::factory()->create([
            'passhash_algo' => PasswordHasher::ALGO_MD5,
            'last_login' => now(),
        ]);

        $command = $this->artisan('users:legacy-password-report', ['--days-since-login' => 30]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->doesntExpectOutputToContain((string) $recent->username)
            ->assertExitCode(0);
    }

    public function test_legacy_password_report_empty_when_filter_matches_nothing(): void
    {
        $command = $this->artisan('users:legacy-password-report', ['--algo' => 'no-such-algo']);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('No legacy password hashes found.')
            ->assertExitCode(0);
    }

    public function test_outbox_dispatch_reports_counts(): void
    {
        $command = $this->artisan('outbox:dispatch', ['--batch' => 5]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Dispatching up to 5 outbox events...')
            ->assertExitCode(0);
    }
}
