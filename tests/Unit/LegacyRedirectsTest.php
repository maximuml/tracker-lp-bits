<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Wave 6 Step 28: legacy .php URL redirects.
 *
 * Verifies that:
 * - nginx config has 301-redirects for legacy .php URLs to canonical routes
 * - announce.php and scrape.php are NOT redirected (external BT clients)
 * - CI smoke tests use canonical URLs (not .php)
 * - k6 baseline uses canonical URLs
 * - root route redirects to /index (not /index.php)
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class LegacyRedirectsTest extends TestCase
{
    /**
     * nginx config has 301-redirects for key .php URLs.
     * /index.php is NOT redirected — it's the Laravel front controller.
     */
    public function test_nginx_has_legacy_redirects(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        $this->assertStringContainsString('return 301 /login', $conf, 'nginx must redirect /login.php');
        $this->assertStringContainsString('return 301 /signup', $conf, 'nginx must redirect /signup.php');
        $this->assertStringContainsString('return 301 /torrents', $conf, 'nginx must redirect /torrents.php');
        $this->assertStringContainsString('return 301 /forums', $conf, 'nginx must redirect /forums.php');
        $this->assertStringContainsString('return 301 /faq', $conf, 'nginx must redirect /faq.php');
        $this->assertStringContainsString('return 301 /rules', $conf, 'nginx must redirect /rules.php');
    }

    /**
     * /index.php is NOT redirected (it's the Laravel front controller).
     */
    public function test_index_php_not_redirected(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        $this->assertStringNotContainsString('location = /index.php { return 301', $conf, '/index.php must not be redirected');
    }

    /**
     * Form action endpoints (takesignup.php, takelogin.php, etc.) are NOT redirected.
     * They are POST targets, not page URLs.
     */
    public function test_form_actions_not_redirected(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        $this->assertStringNotContainsString('location = /takesignup.php { return 301', $conf, '/takesignup.php is a POST form action, not a page');
        $this->assertStringNotContainsString('location = /takelogin.php { return 301', $conf, '/takelogin.php is a POST form action, not a page');
    }

    /**
     * announce.php is NOT redirected (external BT clients need it).
     */
    public function test_announce_not_redirected(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        // announce.php should be handled by its own location block, not redirected
        $this->assertStringContainsString('location ~ ^/announce(?:\.php)?$', $conf);
        // Should NOT have a 301 redirect for announce
        $this->assertStringNotContainsString('return 301 /announce', $conf);
    }

    /**
     * scrape.php is NOT redirected (external BT clients need it).
     */
    public function test_scrape_not_redirected(): void
    {
        $conf = file_get_contents(base_path('.docker/openresty/sites/app.conf.template'));
        $this->assertStringContainsString('location ~ ^/scrape(?:\.php)?$', $conf);
        $this->assertStringNotContainsString('return 301 /scrape', $conf);
    }

    /**
     * CI smoke tests use canonical URLs.
     */
    public function test_ci_smoke_uses_canonical_urls(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString('/index', $ci, 'CI smoke must use /index');
        $this->assertStringContainsString('/login', $ci, 'CI smoke must use /login');
        $this->assertStringContainsString('/signup', $ci, 'CI smoke must use /signup');
        $this->assertStringContainsString('/torrents', $ci, 'CI smoke must use /torrents');
        // Should not use .php URLs in smoke test loop
        $this->assertStringNotContainsString('/index.php', $ci, 'CI smoke must not use /index.php');
        $this->assertStringNotContainsString('/login.php', $ci, 'CI smoke must not use /login.php');
    }

    /**
     * The browser-smoke CI job runs the Playwright suite that replaced
     * a11y.yml — its specs scan the pages the axe CLI used to cover.
     */
    public function test_browser_smoke_ci_runs_playwright(): void
    {
        $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString('browser-smoke', $ci, 'CI must have the browser-smoke job');
        $this->assertStringContainsString('npx playwright test', $ci, 'browser-smoke must run playwright');
        $this->assertFileExists(
            base_path('tests/browser/specs/a11y.spec.ts'),
            'a11y coverage must live in tests/browser',
        );
    }

    /**
     * k6 baseline uses canonical URLs.
     */
    public function test_k6_baseline_uses_canonical_urls(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString('${BASE_URL}/index', $baseline, 'k6 must use /index');
        $this->assertStringContainsString('${BASE_URL}/login', $baseline, 'k6 must use /login');
        $this->assertStringContainsString('${BASE_URL}/torrents', $baseline, 'k6 must use /torrents');
        $this->assertStringNotContainsString('${BASE_URL}/index.php', $baseline, 'k6 must not use /index.php');
        $this->assertStringNotContainsString('${BASE_URL}/login.php', $baseline, 'k6 must not use /login.php');
        $this->assertStringNotContainsString('${BASE_URL}/torrents.php', $baseline, 'k6 must not use /torrents.php');
    }

    /**
     * Root route redirects to /index (not /index.php).
     */
    public function test_root_route_redirects_to_index(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("redirect('/index')", $routes, 'Root must redirect to /index');
        $this->assertStringNotContainsString("redirect('index.php')", $routes, 'Root must not redirect to index.php');
    }
}
