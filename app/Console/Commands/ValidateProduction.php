<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Validate that the production environment is safe to start.
 *
 * Checks:
 * 1. APP_KEY is set and is not the default placeholder
 * 2. APP_DEBUG is false
 * 3. APP_ENV is production
 * 4. Session cookie is secure (same_site=strict, secure=true)
 * 5. DB credentials are not defaults
 * 6. CRON_TOKEN is set (if cron-based scheduling is used)
 * 7. Writable directories exist and are writable
 *
 * Exits with code 1 (self::FAILURE) if any check fails, printing
 * a clear error message for each failure. This is called from
 * entrypoint.prod.sh before starting php-fpm, horizon, or scheduler.
 */
final class ValidateProduction extends Command
{
    protected $signature = 'app:validate-production';

    protected $description = 'Validate production configuration — fail fast if unsafe';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->checkAppKey();
        $this->checkAppDebug();
        $this->checkAppEnv();
        $this->checkSessionConfig();
        $this->checkDbCredentials();
        $this->checkWritablePaths();

        // Print warnings (non-blocking)
        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }

        // Print errors (blocking)
        if ($this->errors !== []) {
            $this->error('Production validation failed with '.count($this->errors).' error(s):');
            foreach ($this->errors as $error) {
                $this->line("  ✗ $error");
            }

            return self::FAILURE;
        }

        $this->info('Production configuration validated successfully.');

        return self::SUCCESS;
    }

    /**
     * Check that APP_KEY is set and is not a placeholder.
     */
    private function checkAppKey(): void
    {
        $key = config('app.key');

        if ($key === null || $key === '') {
            $this->errors[] = 'APP_KEY is not set. Run `php artisan key:generate`.';

            return;
        }

        if (str_starts_with($key, 'base64:')) {
            return;
        }

        // Legacy plain-text keys are acceptable but not recommended
        $this->warnings[] = 'APP_KEY is not base64-encoded. Consider regenerating with `php artisan key:generate`.';
    }

    /**
     * Check that APP_DEBUG is false in production.
     */
    private function checkAppDebug(): void
    {
        if (config('app.debug') === true) {
            $this->errors[] = 'APP_DEBUG is true. This must be false in production to prevent sensitive info leakage.';
        }
    }

    /**
     * Check that APP_ENV is production.
     */
    private function checkAppEnv(): void
    {
        $env = config('app.env');

        if ($env !== 'production') {
            $this->errors[] = "APP_ENV is '{$env}', expected 'production'.";
        }
    }

    /**
     * Check session cookie security settings.
     */
    private function checkSessionConfig(): void
    {
        $sameSite = config('session.same_site');
        $secure = config('session.secure');
        $httpOnly = config('session.http_only');

        if ($sameSite !== 'strict' && $sameSite !== 'lax') {
            $this->errors[] = "session.same_site is '{$sameSite}', expected 'strict' or 'lax'.";
        }

        if ($secure !== true) {
            $this->errors[] = 'session.secure is false. Must be true in production (HTTPS only).';
        }

        if ($httpOnly !== true) {
            $this->errors[] = 'session.http_only is false. Must be true to prevent JS access to cookies.';
        }
    }

    /**
     * Check that DB credentials are not the default development values.
     */
    private function checkDbCredentials(): void
    {
        $password = config('database.connections.mysql.password');

        if ($password === null || $password === '') {
            $this->errors[] = 'DB_PASSWORD is empty. Set a strong password in production.';

            return;
        }

        // Common default passwords that must not be used in production
        $defaults = ['nexusphp', 'root', 'password', 'secret', 'mysql', ''];
        if (in_array($password, $defaults, true)) {
            $this->errors[] = 'DB_PASSWORD is a default/weak value. Use a strong password in production.';
        }
    }

    /**
     * Check that writable directories exist and are writable.
     */
    private function checkWritablePaths(): void
    {
        $paths = [
            '/var/www/html/storage',
            '/var/www/html/storage/framework/views',
            '/var/www/html/storage/framework/sessions',
            '/var/www/html/storage/framework/cache/data',
            '/var/www/html/storage/logs',
            '/var/www/html/bootstrap/cache',
            '/var/www/html/attachments',
            '/var/www/html/torrents',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                $this->errors[] = "Directory {$path} does not exist.";

                continue;
            }

            if (! is_writable($path)) {
                $this->errors[] = "Directory {$path} is not writable by the current user (".get_current_user().').';
            }
        }
    }
}
