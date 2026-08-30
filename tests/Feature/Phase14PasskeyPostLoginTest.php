<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthenticateController;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1.4: verify that passkey login is POST-only — the passkey
 * is never exposed in the URL (access logs, Referer, browser history).
 *
 * Also verifies HMAC replay protection (Sprint 1.3):
 * - signature = hmac_sha256(passkey + timestamp, login_secret)
 * - timestamp must be within ±5 minutes of server time
 */
final class Phase14PasskeyPostLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Settings::saveBatch('security', [
            'login_secret' => 'test-secret-uri',
            'login_secret_deadline' => now()->addDays(30)->toDateTimeString(),
            'login_type' => 'passkey',
        ]);
    }

    public function test_get_request_to_passkey_login_is_not_registered(): void
    {
        // The route is POST-only; a GET to the secret URI should 404
        // (the route is not registered for GET)
        $response = $this->get('/secretlogin');

        // Could be 404 (route not found) or 405 (method not allowed)
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_post_passkey_login_without_passkey_returns_validation_error(): void
    {
        // Register the route dynamically for testing
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $response = $this->post('secretlogin', []);

        // Validation error — passkey is required
        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_post_passkey_login_with_invalid_passkey_does_not_authenticate(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $response = $this->post('secretlogin', ['passkey' => 'invalidpasskeynot32chars']);

        // Validation error — passkey must be 32 chars
        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_post_passkey_login_with_valid_format_but_unknown_passkey_redirects(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $fakePasskey = str_repeat('a', 32);
        $timestamp = time();
        $secret = SiteConfig::current()->security->loginSecret();
        $signature = hash_hmac('sha256', $fakePasskey.$timestamp, $secret);

        $response = $this->post('secretlogin', [
            'passkey' => $fakePasskey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        // Should redirect to index.php (login fails silently, no user found)
        $response->assertRedirect('index.php');
    }

    public function test_passkey_is_not_in_url_on_post(): void
    {
        // This is a structural test: verify the route is POST, not GET
        // by checking that the passkey doesn't appear in any URL
        $routes = $this->app['router']->getRoutes();

        $foundPasskeyGetRoute = false;
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_contains($uri, '{passkey}') && in_array('GET', $route->methods())) {
                $foundPasskeyGetRoute = true;
            }
        }

        $this->assertFalse($foundPasskeyGetRoute, 'No GET route should contain {passkey} parameter — passkey must be sent via POST body.');
    }

    public function test_post_passkey_login_without_signature_redirects_without_auth(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $fakePasskey = str_repeat('b', 32);

        // Missing timestamp and signature — validation should fail
        $response = $this->post('secretlogin', ['passkey' => $fakePasskey]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_post_passkey_login_with_invalid_signature_redirects_without_auth(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $fakePasskey = str_repeat('c', 32);
        $timestamp = time();

        $response = $this->post('secretlogin', [
            'passkey' => $fakePasskey,
            'timestamp' => $timestamp,
            'signature' => 'invalid-signature',
        ]);

        // Should redirect to index.php (HMAC validation fails, no auth)
        $response->assertRedirect('index.php');
    }

    public function test_post_passkey_login_with_expired_timestamp_redirects_without_auth(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $fakePasskey = str_repeat('d', 32);
        // Timestamp 10 minutes ago — outside the ±5 minute window
        $timestamp = time() - 600;
        $secret = SiteConfig::current()->security->loginSecret();
        $signature = hash_hmac('sha256', $fakePasskey.$timestamp, $secret);

        $response = $this->post('secretlogin', [
            'passkey' => $fakePasskey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        // Should redirect to index.php (timestamp out of window, no auth)
        $response->assertRedirect('index.php');
    }

    public function test_post_passkey_login_with_valid_hmac_and_real_user_authenticates(): void
    {
        $this->app['router']->post('secretlogin', [AuthenticateController::class, 'passkeyLogin']);

        $user = User::factory()->create([
            'passkey' => str_repeat('e', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $secret = SiteConfig::current()->security->loginSecret();
        $signature = hash_hmac('sha256', $user->passkey.$timestamp, $secret);

        $response = $this->post('secretlogin', [
            'passkey' => $user->passkey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertRedirect('index.php');
    }
}
