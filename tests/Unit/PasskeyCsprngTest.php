<?php

namespace Tests\Unit;

use App\Support\Security\PasskeyGenerator;
use Tests\TestCase;

/**
 * Wave 2 Steps 8+30: Passkey CSPRNG generation and centralized
 * PasskeyGenerator.
 *
 * All passkey generation must use the centralized PasskeyGenerator
 * (CSPRNG via random_bytes(16) → 32 hex chars), not the legacy
 * md5($username . date() . $passhash) pattern.
 */
final class PasskeyCsprngTest extends TestCase
{
    public function test_passkey_generator_produces_32_hex_chars(): void
    {
        $generator = new PasskeyGenerator;
        $passkey = $generator->generate();

        $this->assertEquals(32, strlen($passkey), 'Passkey must be 32 characters');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $passkey, 'Passkey must be lowercase hex');
    }

    public function test_two_generations_produce_different_passkeys(): void
    {
        $generator = new PasskeyGenerator;
        $key1 = $generator->generate();
        $key2 = $generator->generate();

        $this->assertNotEquals($key1, $key2, 'Each generation must produce a unique passkey');
    }

    public function test_passkey_generator_uses_csprng(): void
    {
        // Verify the generate() method uses random_bytes, not md5/sha1
        $reflection = new \ReflectionClass(PasskeyGenerator::class);
        $method = $reflection->getMethod('generate');
        $source = $reflection->getFileName();
        $content = file_get_contents($source);
        // Extract just the method body to avoid matching comments
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = explode("\n", $content);
        $methodBody = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        $this->assertStringContainsString('random_bytes', $methodBody, 'PasskeyGenerator::generate() must use CSPRNG (random_bytes)');
        $this->assertStringNotContainsString('md5(', $methodBody, 'PasskeyGenerator::generate() must not use md5');
    }

    /**
     * All 7 passkey generation sites must use PasskeyGenerator, not md5().
     */
    public function test_all_passkey_generation_sites_use_passkey_generator(): void
    {
        $filesToCheck = [
            'app/Services/RegistrationService.php',
            'app/Support/LegacyAuth.php',
            'app/Repositories/UserRepository.php',
            'app/Repositories/UsercpRepository.php',
            'app/Http/Controllers/TorrentDownloadController.php',
            'app/Http/Controllers/StaffModerationController.php',
        ];
        foreach ($filesToCheck as $relativePath) {
            $source = file_get_contents(base_path($relativePath));
            $this->assertStringContainsString(
                'PasskeyGenerator',
                $source,
                "$relativePath must use PasskeyGenerator for passkey generation"
            );
        }
    }

    /**
     * No passkey generation should use the legacy md5 pattern.
     * (md5 is still used for cache keys and challenge-response —
     * those are not passkey generation and are acceptable.)
     */
    public function test_no_md5_based_passkey_generation_remains(): void
    {
        // Search for patterns like 'passkey' => md5(...) or ->passkey = md5(...)
        $directories = [app_path('Services'), app_path('Repositories'), app_path('Http/Controllers'), app_path('Support')];
        foreach ($directories as $dir) {
            $this->assertDirectoryExists($dir);
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($rii as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getRealPath());
                // Check for md5(...) assigned to passkey — not cache keys or hashes
                if (preg_match('/[\'"]passkey[\'"]\s*=>\s*md5\s*\(/', $content) ||
                    preg_match('/->passkey\s*=\s*md5\s*\(/', $content)) {
                    $this->fail("{$file->getFilename()} still uses md5() for passkey generation");
                }
            }
        }
        $this->assertTrue(true, 'No md5-based passkey generation found');
    }

    /**
     * Passkey login uses HMAC-SHA256 with constant-time comparison.
     */
    public function test_passkey_login_uses_hmac_and_hash_equals(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AuthenticateController.php'));
        $this->assertStringContainsString('hash_hmac', $source, 'Passkey login must use HMAC');
        $this->assertStringContainsString('sha256', $source, 'Passkey login must use SHA-256');
        $this->assertStringContainsString('hash_equals', $source, 'Passkey login must use constant-time comparison');
    }

    /**
     * Passkey login has timestamp window validation (±5 minutes).
     */
    public function test_passkey_login_has_timestamp_window(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AuthenticateController.php'));
        $this->assertStringContainsString('abs($now - $timestamp)', $source, 'Passkey login must validate timestamp window');
        $this->assertStringContainsString('300', $source, 'Passkey login must use ±300 seconds (5 min) window');
    }

    /**
     * Passkey login has Cache-based replay protection (atomic add).
     */
    public function test_passkey_login_has_replay_protection(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AuthenticateController.php'));
        $this->assertStringContainsString('passkey_login_used', $source, 'Passkey login must store used signatures for replay detection');
        $this->assertStringContainsString('Cache::add', $source, 'Passkey login must use Cache::add for atomic one-time use');
        $this->assertStringContainsString('300', $source, 'Passkey login replay key must expire with timestamp window');
    }
}
