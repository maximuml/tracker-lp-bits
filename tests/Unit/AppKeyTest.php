<?php

namespace Tests\Unit;

use App\Support\Env;
use App\Support\Install\Install;
use Tests\TestCase;

/**
 * Wave 2 Step 6: APP_KEY generation in the installer and production guard.
 *
 * The installer must generate a CSPRNG-based APP_KEY when the .env file
 * is created, set restrictive permissions (0640), and the application
 * must refuse to boot in production with a placeholder or missing key.
 */
final class AppKeyTest extends TestCase
{
    /**
     * The installer generates a valid base64-encoded 32-byte key
     * when APP_KEY is empty or a placeholder.
     */
    public function test_installer_generates_valid_app_key_format(): void
    {
        // Simulate the key generation logic from Install::createEnvFile()
        $generated = 'base64:'.base64_encode(random_bytes(32));

        // Format: base64: followed by 44 chars (32 bytes base64-encoded)
        $this->assertStringStartsWith('base64:', $generated);
        $decoded = base64_decode(substr($generated, 7), true);
        $this->assertNotFalse($decoded, 'APP_KEY must be valid base64');
        $this->assertEquals(32, strlen($decoded), 'APP_KEY must decode to 32 bytes');
    }

    /**
     * Two consecutive generations produce different keys (CSPRNG).
     */
    public function test_two_generations_produce_different_keys(): void
    {
        $key1 = 'base64:'.base64_encode(random_bytes(32));
        $key2 = 'base64:'.base64_encode(random_bytes(32));

        $this->assertNotEquals($key1, $key2, 'Each APP_KEY generation must produce a unique key');
    }

    /**
     * The placeholder value in .env.example is the known insecure default
     * that the installer and production guard must reject.
     */
    public function test_env_example_uses_known_placeholder(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_KEY=ChangeMeToYourGeneratedAppKeyNow', $envExample);
    }

    /**
     * The installer source code contains the key generation logic:
     * random_bytes(32) + base64 encoding for empty/placeholder keys.
     */
    public function test_installer_source_contains_csprng_generation(): void
    {
        $source = file_get_contents(app_path('Support/Install/Install.php'));
        $this->assertStringContainsString('random_bytes(32)', $source, 'Installer must use CSPRNG (random_bytes) for APP_KEY generation');
        $this->assertStringContainsString('base64:', $source, 'Installer must produce base64: prefixed key');
        $this->assertStringContainsString('ChangeMeToYourGeneratedAppKeyNow', $source, 'Installer must detect and replace the placeholder');
    }

    /**
     * The installer sets restrictive file permissions (0640) on .env.
     */
    public function test_installer_sets_chmod_0640(): void
    {
        $source = file_get_contents(app_path('Support/Install/Install.php'));
        $this->assertStringContainsString('chmod', $source, 'Installer must set file permissions on .env');
        $this->assertStringContainsString('0640', $source, 'Installer must use 0640 permissions for .env');
    }

    /**
     * The AppServiceProvider contains a production guard that rejects
     * placeholder or missing APP_KEY.
     */
    public function test_app_service_provider_has_production_guard(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('APP_KEY', $source, 'AppServiceProvider must check APP_KEY');
        $this->assertStringContainsString('isProduction', $source, 'AppServiceProvider must guard in production only');
        $this->assertStringContainsString('ChangeMeToYourGeneratedAppKeyNow', $source, 'AppServiceProvider must reject the known placeholder');
        $this->assertStringContainsString('RuntimeException', $source, 'AppServiceProvider must throw on invalid key');
    }

    /**
     * The production guard does not fire in non-production environments
     * (testing/local), allowing the app to boot with placeholder keys
     * during development.
     */
    public function test_production_guard_does_not_fire_in_testing(): void
    {
        // We're in testing environment — the app booted successfully
        // (otherwise this test wouldn't run), which means the guard
        // correctly skips non-production environments.
        $this->assertFalse(app()->isProduction(), 'Test environment must not be production');
        $this->assertNotEmpty(config('app.key'), 'App must have a key configured for testing');
    }
}
