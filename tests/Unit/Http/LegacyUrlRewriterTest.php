<?php

namespace Tests\Unit\Http;

use App\Http\LegacyUrlRewriter;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
class LegacyUrlRewriterTest extends TestCase
{
    public function test_health_subpaths_are_preserved_for_laravel_routing(): void
    {
        $request = Request::create('http://localhost/health/diag', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/health/diag', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/health.php', $rewritten->server->get('SCRIPT_NAME'));
    }

    public function test_metrics_path_is_preserved_for_laravel_routing(): void
    {
        $request = Request::create('http://localhost/metrics', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/metrics', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/metrics.php', $rewritten->server->get('SCRIPT_NAME'));
    }

    public function test_recover_reset_subpath_is_preserved_for_laravel_routing(): void
    {
        $request = Request::create('http://localhost/recover/reset', 'POST', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/recover/reset', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/recover.php', $rewritten->server->get('SCRIPT_NAME'));
    }

    public function test_legacy_recover_php_path_still_routes_to_recover(): void
    {
        $request = Request::create('http://localhost/recover.php?id=1&secret=abc', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/recover?id=1&secret=abc', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/recover.php', $rewritten->server->get('SCRIPT_NAME'));
    }

    public function test_legacy_script_paths_still_collapse_to_first_segment(): void
    {
        $request = Request::create('http://localhost/details.php?id=5', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/details/5', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/details.php', $rewritten->server->get('SCRIPT_NAME'));
    }

    public function test_web_paths_carry_the_page_script_for_legacy_context(): void
    {
        $request = Request::create('http://localhost/web/torrents?seeded=1', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/web/torrents?seeded=1', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/index.php', $rewritten->server->get('SCRIPT_NAME'));
        $this->assertSame('torrents', $rewritten->server->get('LEGACY_PAGE_SCRIPT'));
    }

    public function test_non_web_passthrough_paths_do_not_carry_a_page_script(): void
    {
        $request = Request::create('http://localhost/api/ping', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertNull($rewritten->server->get('LEGACY_PAGE_SCRIPT'));
    }

    public function test_auth_passkey_subpath_is_preserved_for_laravel_routing(): void
    {
        // SEC-03: /auth/passkey collapsed to /auth before reaching the
        // router, so the passkey-login v2 endpoint was unreachable.
        $request = Request::create('http://localhost/auth/passkey', 'POST', server: [
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);

        $rewritten = (new LegacyUrlRewriter)->rewrite($request);

        $this->assertSame('/auth/passkey', $rewritten->server->get('REQUEST_URI'));
        $this->assertSame('/auth.php', $rewritten->server->get('SCRIPT_NAME'));
    }
}
