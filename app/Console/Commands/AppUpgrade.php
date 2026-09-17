<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Installer\EnvFileWriter;
use App\Services\Installer\InstallRequirements;
use App\Services\Installer\InstallService;
use App\Services\Installer\UpgradeService;
use Illuminate\Console\Command;

/**
 * Post-pull upgrade — replaces `nexus:update` and the legacy web updater.
 *
 * Code arrives via `git pull`; this command performs the housekeeping
 * that used to run after the code download: merge new .env keys, create
 * symlinks, run the conditional legacy data fixups, migrate, and run the
 * extra post-migration work.
 */
class AppUpgrade extends Command
{
    protected $signature = 'app:upgrade
        {--skip-requirements : Skip the PHP/extension/function checks}';

    protected $description = 'Upgrade the application after a code pull: .env merge, fixups, migrate';

    public function handle(
        InstallRequirements $requirements,
        EnvFileWriter $envWriter,
        InstallService $installer,
        UpgradeService $upgrade,
    ): int {
        $log = fn (string $message) => $this->info($message);

        if (! $this->option('skip-requirements')) {
            $result = $requirements->check();
            if (! $result['pass']) {
                $this->error('Environment requirements not met:');
                foreach ($result['fails'] as $fail) {
                    $this->error("  {$fail['label']} — required: {$fail['required']}, current: {$fail['current']}");
                }

                return self::FAILURE;
            }
        }

        $this->info('Merging new .env keys ...');
        $envWriter->mergeNewKeys($log);

        $this->info('Creating symbolic links ...');
        // resolveSettings only computes the symlink targets — settings are
        // NOT re-saved on upgrade (operators' values must be preserved).
        $installer->createSymbolicLinks($installer->resolveSettings()['symbolic_links'], $log);

        $this->info('Running legacy data fixups ...');
        $upgrade->runLegacyFixups($log);

        $this->info('Running migrations ...');
        $installer->migrate();

        $this->info('Running extra post-migration work ...');
        $upgrade->runExtraMigrate($log);

        $this->info('Upgrade complete.');

        return self::SUCCESS;
    }
}
