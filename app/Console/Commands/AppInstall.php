<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Installer\EnvFileWriter;
use App\Services\Installer\InstallRequirements;
use App\Services\Installer\InstallService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh install — the CLI replacement for the legacy web wizard
 * (app/Support/Install/scripts/install.php).
 *
 * Order mirrors the wizard: requirements -> .env -> migrate + seed ->
 * settings merge -> symlinks -> searchbox modes -> tracker URL -> admin.
 */
class AppInstall extends Command
{
    protected $signature = 'app:install
        {--username= : Administrator username}
        {--email= : Administrator email}
        {--password= : Administrator password}
        {--env= : Extra .env overrides as KEY=VALUE,KEY=VALUE}
        {--force : Run even when the database is already installed}
        {--skip-requirements : Skip the PHP/extension/function checks}';

    protected $description = 'Install the application: .env, migrations, seed, settings, admin';

    public function handle(
        InstallRequirements $requirements,
        EnvFileWriter $envWriter,
        InstallService $installer,
    ): int {
        $log = fn (string $message) => $this->info($message);

        if (! $this->option('skip-requirements')) {
            $result = $requirements->check();
            foreach ($result['rows'] as $row) {
                $this->line(sprintf('  [%s] %s (required: %s, current: %s)', $row['result'], $row['label'], $row['required'], $row['current']));
            }
            if (! $result['pass']) {
                $this->error('Environment requirements not met:');
                foreach ($result['fails'] as $fail) {
                    $this->error("  {$fail['label']} — required: {$fail['required']}, current: {$fail['current']}");
                }

                return self::FAILURE;
            }
        }

        // .env: create from .env.example when missing, merge overrides.
        $overrides = $this->parseEnvOverrides();
        if (! file_exists(base_path('.env')) || $overrides !== []) {
            $this->info('Writing .env ...');
            $envWriter->write($overrides, freshInstall: true, log: $log);
        }

        // Refuse to clobber an installed database without --force.
        if (Schema::hasTable('users') && DB::table('users')->count() > 0 && ! $this->option('force')) {
            $this->error('Database is already installed (users table is not empty). Pass --force to reinstall.');

            return self::FAILURE;
        }

        $this->info('Running migrations ...');
        $installer->migrate();
        $this->info('Running seeders ...');
        $installer->seed();

        $this->info('Resolving and saving settings ...');
        $resolved = $installer->resolveSettings();
        $installer->saveSettings($resolved['settings'], $log);
        $installer->createSymbolicLinks($resolved['symbolic_links'], $log);

        $installer->migrateSearchBoxModeRelated($log);
        $installer->initTrackerUrl('install', $log);

        $username = $this->option('username');
        $email = $this->option('email');
        $password = $this->option('password');
        if ($username !== null && $email !== null && $password !== null) {
            $user = $installer->createAdministrator($username, $email, $password, $password);
            $this->info("Administrator created: {$user->id} {$user->username}");
        } elseif ($username !== null || $email !== null || $password !== null) {
            $this->warn('Administrator not created: --username, --email and --password must all be given.');
        } else {
            $this->warn('No administrator credentials given — create one later with user:reset_id_auto_increment or via Filament.');
        }

        $this->info('Install complete.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvOverrides(): array
    {
        $overrides = [];
        foreach (explode(',', (string) $this->option('env')) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $pair, 2);
            $overrides[trim($key)] = trim($value);
        }

        return $overrides;
    }
}
