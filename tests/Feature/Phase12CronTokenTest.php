<?php

namespace Tests\Feature;

use App\Http\Middleware\CronToken;
use Illuminate\Contracts\Http\Kernel;
use Tests\TestCase;

/**
 * Phase 1.2: verify that the /cron endpoint is protected by the
 * CronToken middleware — only loopback or valid token requests are allowed.
 */
final class Phase12CronTokenTest extends TestCase
{
    public function test_cron_endpoint_rejects_external_request_without_token(): void
    {
        // Simulate a non-loopback request without a token
        $response = $this->get('/cron', ['REMOTE_ADDR' => '203.0.113.1']);

        $this->assertEquals(403, $response->status());
        $this->assertSame('Forbidden', $response->getContent());
    }

    public function test_cron_endpoint_rejects_external_request_with_wrong_token(): void
    {
        config(['app.cron_token' => 'correct-secret-token']);

        $response = $this->get('/cron?token=wrong-token', ['REMOTE_ADDR' => '203.0.113.1']);

        $this->assertEquals(403, $response->status());
    }

    public function test_cron_endpoint_rejects_external_request_with_empty_token_when_config_set(): void
    {
        config(['app.cron_token' => 'correct-secret-token']);

        $response = $this->get('/cron?token=', ['REMOTE_ADDR' => '203.0.113.1']);

        $this->assertEquals(403, $response->status());
    }

    public function test_cron_token_middleware_is_registered(): void
    {
        $kernel = app(Kernel::class);
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('middlewareAliases');
        $property->setAccessible(true);
        $aliases = $property->getValue($kernel);

        $this->assertArrayHasKey('cron.token', $aliases);
        $this->assertSame(CronToken::class, $aliases['cron.token']);
    }

    public function test_cron_route_has_cron_token_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('cron.legacy');

        $this->assertNotNull($route, 'cron.legacy route should exist');
        $this->assertContains('cron.token', $route->gatherMiddleware());
    }

    public function test_config_app_has_cron_token_key(): void
    {
        $this->assertArrayHasKey('cron_token', config('app'));
        $this->assertSame('', config('app.cron_token'));
    }

    public function test_env_example_documents_cron_token(): void
    {
        $content = file_get_contents(base_path('.env.example'));
        $this->assertNotFalse($content);
        $this->assertStringContainsString('CRON_TOKEN', $content);
    }
}
