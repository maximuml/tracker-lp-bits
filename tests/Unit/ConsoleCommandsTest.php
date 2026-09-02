<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\BackupCronjob;
use App\Console\Commands\BackupRestoreDrill;
use App\Console\Commands\ExamCheckoutCronjob;
use App\Console\Commands\HitAndRunUpdateStatus;
use App\Console\Commands\TrackerCalculateSeedBonus;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Wave 5 Step 33: Console cron-command registration and signature tests.
 *
 * Verifies that all cron-commands referenced by the scheduler are
 * registered with Artisan and have the correct signature.
 */
final class ConsoleCommandsTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function cronCommands(): array
    {
        return [
            'backup:cronjob' => ['backup:cronjob'],
            'backup:all' => ['backup:all'],
            'backup:database' => ['backup:database'],
            'backup:web' => ['backup:web'],
            'backup:restore-drill' => ['backup:restore-drill'],
            'attendance:cleanup' => ['attendance:cleanup'],
            'hr:update_status' => ['hr:update_status'],
            'tracker:calculate_seed_bonus' => ['tracker:calculate_seed_bonus'],
            'exam:assign_cronjob' => ['exam:assign_cronjob'],
            'exam:checkout_cronjob' => ['exam:checkout_cronjob'],
            'cleanup:run' => ['cleanup:run'],
            'cleanup:tasks' => ['cleanup:tasks'],
            'exam:update_progress' => ['exam:update_progress'],
            'invite:tmp' => ['invite:tmp'],
            'torrent:load_bought_user' => ['torrent:load_bought_user'],
            'torrent:load_pieces_hash' => ['torrent:load_pieces_hash'],
            'user:login_notify' => ['user:login_notify'],
            'user:generate' => ['user:generate'],
            'user:reset_password' => ['user:reset_password'],
            'user:reset_id_auto_increment' => ['user:reset_id_auto_increment'],
            'user:delete_expired_token' => ['user:delete_expired_token'],
            'meilisearch:import' => ['meilisearch:import'],
            'meilisearch:stats' => ['meilisearch:stats'],
            'nexus:update' => ['nexus:update'],
        ];
    }

    /**
     * All cron-commands are registered with Artisan.
     *
     * @dataProvider cronCommands
     */
    #[DataProvider('cronCommands')]
    public function test_cron_command_is_registered(string $command): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey($command, $commands, "Command '$command' must be registered with Artisan");
    }

    /**
     * backup:cronjob has --force option.
     */
    public function test_backup_cronjob_has_force_option(): void
    {
        $reflection = new \ReflectionClass(BackupCronjob::class);
        $signature = (string) ($reflection->getDefaultProperties()['signature'] ?? '');
        $this->assertStringContainsString('backup:cronjob', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    /**
     * tracker:calculate_seed_bonus requires uid argument.
     */
    public function test_tracker_calculate_seed_bonus_requires_uid(): void
    {
        $reflection = new \ReflectionClass(TrackerCalculateSeedBonus::class);
        $signature = (string) ($reflection->getDefaultProperties()['signature'] ?? '');
        $this->assertStringContainsString('tracker:calculate_seed_bonus', $signature);
        $this->assertStringContainsString('{uid}', $signature);
    }

    /**
     * hr:update_status has optional uid and torrent_id options.
     */
    public function test_hr_update_status_has_optional_options(): void
    {
        $reflection = new \ReflectionClass(HitAndRunUpdateStatus::class);
        $signature = (string) ($reflection->getDefaultProperties()['signature'] ?? '');
        $this->assertStringContainsString('hr:update_status', $signature);
        $this->assertStringContainsString('--uid=', $signature);
        $this->assertStringContainsString('--torrent_id=', $signature);
        $this->assertStringContainsString('--ignore_time=', $signature);
    }

    /**
     * backup:restore-drill has --latest, --file, --test-db options.
     */
    public function test_backup_restore_drill_signature(): void
    {
        $reflection = new \ReflectionClass(BackupRestoreDrill::class);
        $signature = (string) ($reflection->getDefaultProperties()['signature'] ?? '');
        $this->assertStringContainsString('backup:restore-drill', $signature);
        $this->assertStringContainsString('--latest', $signature);
        $this->assertStringContainsString('--file=', $signature);
        $this->assertStringContainsString('--test-db=', $signature);
    }

    /**
     * exam:checkout_cronjob has --ignore-time-range option.
     */
    public function test_exam_checkout_cronjob_has_ignore_time_range(): void
    {
        $reflection = new \ReflectionClass(ExamCheckoutCronjob::class);
        $signature = (string) ($reflection->getDefaultProperties()['signature'] ?? '');
        $this->assertStringContainsString('exam:checkout_cronjob', $signature);
        $this->assertStringContainsString('--ignore-time-range', $signature);
    }

    /**
     * The Console\Kernel.php schedules the key cron-commands.
     */
    public function test_kernel_schedules_key_cron_commands(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('backup:cronjob', $kernel);
        $this->assertStringContainsString('withoutOverlapping', $kernel);
        $this->assertStringContainsString('onOneServer', $kernel);
    }

    /**
     * All backup commands exist.
     */
    public function test_all_backup_commands_exist(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('backup:all', $commands);
        $this->assertArrayHasKey('backup:database', $commands);
        $this->assertArrayHasKey('backup:web', $commands);
        $this->assertArrayHasKey('backup:cronjob', $commands);
        $this->assertArrayHasKey('backup:restore-drill', $commands);
    }
}
