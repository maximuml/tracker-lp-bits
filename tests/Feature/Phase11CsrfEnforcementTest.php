<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1.1: verify that CSRF protection is enforced on form-based routes
 * and that the blanket $except list has been reduced to only webhooks and
 * AJAX endpoints.
 */
final class Phase11CsrfEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_csrf_meta_tag_present_in_main_layout(): void
    {
        $view = view('layui-page')->render();
        $this->assertStringContainsString('name="csrf-token"', $view);
    }

    public function test_csrf_meta_tag_present_in_auth_layout(): void
    {
        $view = view('layouts.auth')->render();
        $this->assertStringContainsString('name="csrf-token"', $view);
    }

    public function test_csrf_js_file_exists(): void
    {
        $this->assertFileExists(public_path('js/csrf.js'));
    }

    public function test_post_without_csrf_token_to_protected_route_returns_419(): void
    {
        // 'takeupload' was previously in $except — now it should require CSRF
        $response = $this->post('/takeupload', []);

        // 419 = CSRF token mismatch (or 302 redirect to login if auth fails first)
        // The important thing is it's NOT a 200/500 that would indicate CSRF is disabled
        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_post_without_csrf_token_to_modtask_returns_419(): void
    {
        // 'modtask' is an admin route that was previously CSRF-exempt
        $response = $this->post('/modtask', []);

        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_post_without_csrf_token_to_clearcache_returns_419(): void
    {
        $response = $this->post('/clearcache', []);

        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_post_without_csrf_token_to_adduser_returns_419(): void
    {
        $response = $this->post('/adduser', []);

        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_post_without_csrf_token_to_settings_returns_419(): void
    {
        $response = $this->post('/settings', []);

        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_post_without_csrf_token_to_takeinvite_returns_419(): void
    {
        $response = $this->post('/takeinvite', []);

        $this->assertContains($response->status(), [419, 302]);
    }

    public function test_ajax_route_still_exempt_from_csrf(): void
    {
        // 'ajax' should still be exempt — it's used by fetch() without CSRF headers
        $response = $this->post('/ajax', ['action' => 'nonexistent']);

        // Should NOT get 419 — ajax is exempt
        $this->assertNotEquals(419, $response->status());
    }

    public function test_except_list_only_contains_webhooks_and_ajax(): void
    {
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);
        $except = $property->getValue($middleware);

        // Should be a small list — webhooks + AJAX endpoints only
        $this->assertLessThanOrEqual(10, count($except), 'CSRF $except list should be small (webhooks + AJAX only), got: '.implode(', ', $except));

        // Should NOT contain form-based admin routes
        $this->assertNotContains('modtask', $except);
        $this->assertNotContains('adduser', $except);
        $this->assertNotContains('clearcache', $except);
        $this->assertNotContains('takeupload', $except);
        $this->assertNotContains('settings', $except);
        $this->assertNotContains('takeinvite', $except);
    }

    public function test_get_request_to_modtask_returns_405(): void
    {
        // modtask performs high-privilege user edits and must not be
        // reachable via GET (CSRF bypass vector). The reject.get.mutations
        // middleware returns 405 for GET/HEAD.
        $response = $this->get('/modtask');

        $this->assertContains($response->status(), [405, 302]);
    }

    public function test_get_request_to_poll_delete_shows_confirmation_form_not_delete(): void
    {
        // Poll delete via GET must NOT delete — it should show a
        // confirmation form with a POST button instead.
        $response = $this->get('/log?action=poll&do=delete&pollid=1&sure=1');

        // Should not be a redirect to /log.php?action=poll&deleted=1
        // (which would indicate the poll was deleted via GET).
        // Expected: 200 (confirmation page) or 302 (login redirect).
        $this->assertContains($response->status(), [200, 302]);
        if ($response->status() === 200) {
            // The confirmation page should contain a POST form, not a GET link
            $this->assertStringContainsString('method="post"', $response->getContent());
            $this->assertStringNotContainsString('deleted=1', $response->headers->get('Location', ''));
        }
    }
}
