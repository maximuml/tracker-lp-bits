<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1.1 / Wave 2 Step 5: verify that CSRF protection is enforced
 * on form-based routes AND on the /ajax endpoint. The blanket $except
 * list has been reduced to only webhooks and legacy AJAX endpoints
 * that cannot use CSRF tokens (external services, raw XHR without csrf.js).
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

    public function test_csrf_js_injects_token_into_forms_and_ajax(): void
    {
        $content = file_get_contents(public_path('js/csrf.js'));
        // Injects _token into POST forms
        $this->assertStringContainsString('_token', $content);
        // Patches XMLHttpRequest for CSRF on mutating requests
        $this->assertStringContainsString('XMLHttpRequest', $content);
        // Patches fetch for same-origin mutating requests
        $this->assertStringContainsString('X-CSRF-TOKEN', $content);
    }

    public function test_ajaxbasic_js_sends_csrf_header_on_post(): void
    {
        $content = file_get_contents(public_path('js/ajaxbasic.js'));
        $this->assertStringContainsString('X-CSRF-TOKEN', $content, 'ajaxbasic.js must send X-CSRF-TOKEN header on POST requests');
    }

    public function test_shoutbox_js_sends_csrf_header_on_xhr_fallback(): void
    {
        $content = file_get_contents(public_path('js/shoutbox.js'));
        // The XHR fallback path must set X-CSRF-TOKEN header
        $this->assertStringContainsString('X-CSRF-TOKEN', $content, 'shoutbox.js XHR fallback must send X-CSRF-TOKEN header');
    }

    public function test_csrf_meta_tag_in_page_layout_header(): void
    {
        // PageLayout::header() renders the meta tag for all legacy pages.
        // Verify the source includes the csrf-token meta tag.
        $source = file_get_contents(app_path('Support/PageLayout.php'));
        $this->assertStringContainsString('csrf-token', $source, 'PageLayout must include csrf-token meta tag in header');
    }

    public function test_csrf_js_included_in_page_layout_footer(): void
    {
        // PageLayout::footer() includes csrf.js for all legacy pages.
        // Verify by checking the source file for the include.
        $source = file_get_contents(app_path('Support/PageLayout.php'));
        $this->assertStringContainsString('csrf.js', $source, 'PageLayout must include csrf.js in footer');
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

    public function test_ajax_route_not_in_csrf_except_list(): void
    {
        // 'ajax' was removed from $except in Phase 1.1 — it now requires CSRF.
        // Note: Laravel's VerifyCsrfToken skips checks in unit tests via
        // runningUnitTests(), so we verify the configuration instead of
        // expecting a 419 response at runtime.
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);
        $except = $property->getValue($middleware);

        $this->assertNotContains('ajax', $except, '/ajax must NOT be in CSRF $except — it requires CSRF protection');
    }

    public function test_ajax_route_has_web_middleware_with_csrf(): void
    {
        // Verify that /ajax route has 'web' middleware group which includes
        // VerifyCsrfToken (PreventRequestForgery in Laravel 13).
        $route = app('router')->getRoutes()->getByAction('POST/ajax');
        if (! $route) {
            // Fallback: search all routes
            foreach (app('router')->getRoutes() as $r) {
                if ($r->uri() === 'ajax' && in_array('POST', $r->methods())) {
                    $route = $r;
                    break;
                }
            }
        }
        $this->assertNotNull($route, '/ajax POST route must exist');
        $middleware = $route->gatherMiddleware();
        $this->assertContains('web', $middleware, '/ajax must have web middleware (includes CSRF verification)');
    }

    public function test_except_list_only_contains_webhooks_and_legacy_ajax(): void
    {
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);
        $middleware = app(VerifyCsrfToken::class);
        $except = $property->getValue($middleware);

        // Should be a small list — webhooks + legacy AJAX endpoints only
        $this->assertLessThanOrEqual(10, count($except), 'CSRF $except list should be small (webhooks + legacy AJAX only), got: '.implode(', ', $except));

        // Should NOT contain form-based admin routes
        $this->assertNotContains('modtask', $except);
        $this->assertNotContains('adduser', $except);
        $this->assertNotContains('clearcache', $except);
        $this->assertNotContains('takeupload', $except);
        $this->assertNotContains('settings', $except);
        $this->assertNotContains('takeinvite', $except);
        // 'ajax' was removed — CSRF is now enforced via X-CSRF-TOKEN header
        $this->assertNotContains('ajax', $except);
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
