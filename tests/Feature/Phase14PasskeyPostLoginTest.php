<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthenticateController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1.4: verify that passkey login is POST-only — the passkey
 * is never exposed in the URL (access logs, Referer, browser history).
 */
final class Phase14PasskeyPostLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
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

        $response = $this->post('secretlogin', ['passkey' => $fakePasskey]);

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
}
