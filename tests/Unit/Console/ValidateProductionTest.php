<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * T-14: Tests for the app:validate-production command.
 *
 * The command checks that the production environment is safe to start.
 * In the test environment (APP_ENV=testing), it should detect that
 * APP_DEBUG, APP_ENV, session.secure, and DB_PASSWORD are not production-ready.
 */
final class ValidateProductionTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $exitCode = Artisan::call('list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('app:validate-production', $output);
    }

    public function test_command_fails_in_testing_environment(): void
    {
        // In testing env, APP_ENV is 'testing', not 'production'
        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Production validation failed', $output);
    }

    public function test_command_checks_app_env(): void
    {
        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertStringContainsString('APP_ENV', $output);
    }

    public function test_command_checks_app_debug(): void
    {
        // Temporarily set APP_DEBUG=true
        config(['app.debug' => true]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('APP_DEBUG', $output);
    }

    public function test_command_passes_with_production_config(): void
    {
        // Set all config values to production-safe values
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.same_site' => 'strict',
            'session.secure' => true,
            'session.http_only' => true,
            'database.connections.mysql.password' => 'strong_production_password_123',
        ]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        // May still fail on writable paths in test env, but config checks should pass
        $this->assertStringNotContainsString('APP_KEY', $output);
        $this->assertStringNotContainsString('APP_DEBUG', $output);
        $this->assertStringNotContainsString('APP_ENV', $output);
        $this->assertStringNotContainsString('session.secure', $output);
        $this->assertStringNotContainsString('DB_PASSWORD', $output);
    }

    public function test_command_detects_weak_db_password(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.same_site' => 'strict',
            'session.secure' => true,
            'session.http_only' => true,
            'database.connections.mysql.password' => 'nexusphp',
        ]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('DB_PASSWORD', $output);
        $this->assertStringContainsString('default/weak', $output);
    }

    public function test_command_detects_empty_db_password(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.same_site' => 'strict',
            'session.secure' => true,
            'session.http_only' => true,
            'database.connections.mysql.password' => '',
        ]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('DB_PASSWORD is empty', $output);
    }

    public function test_command_detects_missing_app_key(): void
    {
        config([
            'app.key' => '',
            'app.debug' => false,
            'app.env' => 'production',
            'session.same_site' => 'strict',
            'session.secure' => true,
            'session.http_only' => true,
            'database.connections.mysql.password' => 'strong_password_123',
        ]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('APP_KEY is not set', $output);
    }

    public function test_command_detects_insecure_session(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.same_site' => 'none',
            'session.secure' => false,
            'session.http_only' => false,
            'database.connections.mysql.password' => 'strong_password_123',
        ]);

        $exitCode = Artisan::call('app:validate-production');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('session.same_site', $output);
        $this->assertStringContainsString('session.secure', $output);
        $this->assertStringContainsString('session.http_only', $output);
    }
}
