<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Guarded wrapper around `migrate:fresh` that refuses to run unless
 * the database name contains a test marker ("testing").
 *
 * This prevents accidental data loss on dev or production databases
 * when running tests or CI scripts that call migrate:fresh.
 *
 * Usage:
 *   php artisan test:migrate-fresh [--seed] [--force]
 *
 * This command should be used instead of `migrate:fresh` in all
 * test bootstrap scripts and CI workflows.
 */
class TestMigrateFresh extends Command
{
    protected $signature = 'test:migrate-fresh
        {--seed : Seed the database after migration}
        {--force : Force the operation to run in production environment}';

    protected $description = 'Run migrate:fresh with a test-database guard (refuses non-test DB names)';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database');

        if (! is_string($database) || ! str_contains(strtolower($database), 'testing')) {
            $this->error(
                "Refusing to run migrate:fresh on database '{$database}'. "
                .'The database name must contain "testing" (e.g. nexusphp_testing). '
                .'Set DB_DATABASE in .env.testing or pass it as an environment variable.'
            );

            return self::FAILURE;
        }

        $this->info("Database '{$database}' passed the test-database guard.");

        $args = ['--force' => true];
        if ($this->option('seed')) {
            $args['--seed'] = true;
        }

        $this->call('migrate:fresh', $args);

        return self::SUCCESS;
    }
}
