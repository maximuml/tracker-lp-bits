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
}
