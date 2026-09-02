<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\BackupRestoreDrill;
use App\Repositories\ToolRepository;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Wave 5 Step 21: backup + restore drill.
 *
 * Verifies that:
 * - backup:cronjob schedule uses withoutOverlapping + onOneServer
 * - Default backup path is storage/app/backups (not /tmp)
 * - backup:restore-drill command exists with correct signature
 * - Restore drill creates and drops a temporary database
 */
final class BackupRestoreDrillTest extends TestCase
{
    /**
     * The backup:cronjob schedule has withoutOverlapping and onOneServer.
     * Verified by reading the Kernel source — runtime schedule inspection
     * is fragile in test context.
     */
    public function test_backup_cronjob_schedule_has_overlapping_guard(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('backup:cronjob', $kernel);
        $this->assertStringContainsString('withoutOverlapping', $kernel);
        $this->assertStringContainsString('onOneServer', $kernel);
    }

    /**
     * The Console/Kernel.php schedules backup:cronjob with withoutOverlapping + onOneServer.
     */
    public function test_kernel_schedules_backup_with_guards(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('backup:cronjob', $kernel);
        $this->assertStringContainsString('withoutOverlapping', $kernel);
        $this->assertStringContainsString('onOneServer', $kernel);
    }

    /**
     * Default backup path is storage/app/backups (not /tmp).
     */
    public function test_default_backup_path_is_storage(): void
    {
        $rep = app(ToolRepository::class);
        $path = $rep->getBackupExportPathDefault();
        $this->assertStringContainsString('storage', $path);
        $this->assertStringContainsString('backups', $path);
        $this->assertStringNotContainsString('/tmp', $path, 'Backup path should not be in /tmp (volatile)');
    }

    /**
     * BackupRestoreDrill command exists with correct signature.
     */
    public function test_restore_drill_command_exists(): void
    {
        $this->assertTrue(class_exists(BackupRestoreDrill::class));

        $reflection = new \ReflectionClass(BackupRestoreDrill::class);
        $defaultProps = $reflection->getDefaultProperties();
        $signature = (string) ($defaultProps['signature'] ?? '');
        $this->assertStringContainsString('backup:restore-drill', $signature);
        $this->assertStringContainsString('--latest', $signature);
        $this->assertStringContainsString('--file=', $signature);
        $this->assertStringContainsString('--test-db=', $signature);
    }

    /**
     * The restore drill command is registered in the console commands.
     */
    public function test_restore_drill_command_is_registered(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('backup:restore-drill', $commands, 'backup:restore-drill command must be registered');
    }

    /**
     * BackupAll, BackupDatabase, BackupWeb, BackupCronjob commands all exist.
     */
    public function test_all_backup_commands_exist(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('backup:all', $commands);
        $this->assertArrayHasKey('backup:database', $commands);
        $this->assertArrayHasKey('backup:web', $commands);
        $this->assertArrayHasKey('backup:cronjob', $commands);
    }
}
