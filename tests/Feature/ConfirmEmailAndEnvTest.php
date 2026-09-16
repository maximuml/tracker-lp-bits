<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SecureTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Phase 1.5: verify that the /confirmemail endpoint validates the email
 * address before writing it to the database, and that .env.example is
 * sanitized (no real secrets).
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ConfirmEmailAndEnvTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_confirmemail_rejects_invalid_email_format(): void
    {
        $user = User::factory()->create([
            'editsecret' => 'testsecret12345678901234567890',
        ]);

        $sec = str_pad($user->editsecret, 32, "\0");
        $invalidEmail = 'not-an-email';
        $md5 = md5($sec.$invalidEmail.$sec);

        $response = $this->get("/confirmemail/{$user->id}/{$md5}/".urlencode($invalidEmail));

        // Invalid email should be rejected with 404
        $response->assertNotFound();

        // Email should NOT be updated in the database
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => $invalidEmail,
        ]);
    }

    public function test_confirmemail_rejects_email_exceeding_max_length(): void
    {
        $user = User::factory()->create([
            'editsecret' => 'testsecret12345678901234567890',
        ]);

        $sec = str_pad($user->editsecret, 32, "\0");
        $longEmail = str_repeat('a', 250).'@example.com';
        $md5 = md5($sec.$longEmail.$sec);

        $response = $this->get("/confirmemail/{$user->id}/{$md5}/".urlencode($longEmail));

        $response->assertNotFound();
    }

    public function test_confirmemail_rejects_empty_email(): void
    {
        $user = User::factory()->create([
            'editsecret' => 'testsecret12345678901234567890',
        ]);

        $sec = str_pad($user->editsecret, 32, "\0");
        $md5 = md5($sec.''.$sec);

        // The route pattern requires (.+) so empty email won't match the regex
        // and will 404 before reaching validation
        $response = $this->get("/confirmemail/{$user->id}/{$md5}/");

        $response->assertNotFound();
    }

    public function test_confirmemail_accepts_secure_token(): void
    {
        $user = User::factory()->create();
        $tokenService = app(SecureTokenService::class);

        $token = $tokenService->generate();
        $newEmail = 'newmail@example.com';
        $user->forceFill(['editsecret' => $tokenService->emailChangeDigest($token, $newEmail)])->save();

        $response = $this->get("/confirmemail/{$user->id}/{$token}/".urlencode($newEmail));

        $response->assertRedirect('/usercp.php?action=security&type=saved');
        $user->refresh();
        $this->assertSame($newEmail, $user->email);
        $this->assertSame('', (string) $user->editsecret);
    }

    public function test_confirmemail_rejects_secure_token_with_tampered_email(): void
    {
        $user = User::factory()->create();
        $tokenService = app(SecureTokenService::class);

        $token = $tokenService->generate();
        $user->forceFill(['editsecret' => $tokenService->emailChangeDigest($token, 'intended@example.com')])->save();

        $response = $this->get("/confirmemail/{$user->id}/{$token}/".urlencode('attacker@example.com'));

        $response->assertNotFound();
        $this->assertNotSame('attacker@example.com', $user->refresh()->email);
    }

    public function test_confirmemail_rejects_unknown_secure_token(): void
    {
        $user = User::factory()->create();
        $tokenService = app(SecureTokenService::class);
        $user->forceFill(['editsecret' => $tokenService->emailChangeDigest($tokenService->generate(), 'other@example.com')])->save();

        $response = $this->get("/confirmemail/{$user->id}/".$tokenService->generate().'/'.urlencode('other@example.com'));

        $response->assertNotFound();
    }

    public function test_confirmemail_accepts_legacy_md5_link_during_compat_window(): void
    {
        $user = User::factory()->create([
            'editsecret' => 'deadbeefcafe1234567890abcdef1234567890ab',
        ]);

        $sec = str_pad((string) $user->editsecret, 20);
        $legacyEmail = 'legacy-new@example.com';
        $md5 = md5($sec.$legacyEmail.$sec);

        $response = $this->get("/confirmemail/{$user->id}/{$md5}/".urlencode($legacyEmail));

        $response->assertRedirect('/usercp.php?action=security&type=saved');
        $user->refresh();
        $this->assertSame($legacyEmail, $user->email);
        $this->assertSame('', (string) $user->editsecret);
    }

    public function test_confirmemail_rejects_legacy_md5_link_with_tampered_email(): void
    {
        $user = User::factory()->create([
            'editsecret' => 'deadbeefcafe1234567890abcdef1234567890ab',
        ]);

        $sec = str_pad((string) $user->editsecret, 20);
        $md5 = md5($sec.'intended@example.com'.$sec);

        $response = $this->get("/confirmemail/{$user->id}/{$md5}/".urlencode('attacker@example.com'));

        $response->assertNotFound();
    }

    public function test_confirmemail_rejects_when_editsecret_empty(): void
    {
        $user = User::factory()->create(['editsecret' => '']);
        $token = app(SecureTokenService::class)->generate();

        $response = $this->get("/confirmemail/{$user->id}/{$token}/".urlencode('x@example.com'));

        $response->assertNotFound();
    }

    public function test_env_example_has_no_real_app_key(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('base64:WUbN2wa2kl3E1VDW4iKaH3RBHw3hKY7BK0hWEkBZmGg=', (string) $envExample);
        $this->assertStringNotContainsString('APP_KEY=base64:', (string) $envExample);
    }

    public function test_env_example_app_debug_is_false(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_DEBUG=false', (string) $envExample);
        $this->assertStringNotContainsString('APP_DEBUG=true', (string) $envExample);
    }

    public function test_env_example_has_no_default_meilisearch_key(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('nexusphp_default_key', (string) $envExample);
    }
}
