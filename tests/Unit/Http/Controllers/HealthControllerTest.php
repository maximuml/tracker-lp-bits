<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class HealthControllerTest extends TestCase
{
    public function test_live_returns_ok_status(): void
    {
        $controller = app(HealthController::class);

        $response = $controller->live();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('ok', $body['status']);
    }

    public function test_ready_returns_ok_when_dependencies_healthy(): void
    {
        $controller = app(HealthController::class);

        $response = $controller->ready();

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertIsArray($body['checks']);
        $this->assertArrayHasKey('database', $body['checks']);
        $this->assertArrayHasKey('redis', $body['checks']);
        $this->assertSame('ok', $body['checks']['database']);
        $this->assertSame('ok', $body['checks']['redis']);
    }

    public function test_ready_returns_degraded_when_database_fails(): void
    {
        DB::shouldReceive('connection->getPdo')->andThrow(new \RuntimeException('Connection refused'));

        $controller = app(HealthController::class);

        $response = $controller->ready();

        $this->assertSame(503, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('degraded', $body['status']);
        $this->assertSame('fail', $body['checks']['database']);
    }

    public function test_ready_returns_degraded_when_redis_fails(): void
    {
        Redis::shouldReceive('connection->ping')->andThrow(new \RuntimeException('Connection refused'));

        $controller = app(HealthController::class);

        $response = $controller->ready();

        $this->assertSame(503, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('degraded', $body['status']);
        $this->assertSame('fail', $body['checks']['redis']);
    }

    public function test_ready_includes_meilisearch_check(): void
    {
        $controller = app(HealthController::class);

        $response = $controller->ready();
        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('meilisearch', $body['checks']);
        $this->assertContains($body['checks']['meilisearch'], ['ok', 'degraded', 'skip']);
    }

    public function test_ready_includes_horizon_check(): void
    {
        $controller = app(HealthController::class);

        $response = $controller->ready();
        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('horizon', $body['checks']);
        $this->assertContains($body['checks']['horizon'], ['ok', 'degraded', 'inactive', 'skip']);
    }

    public function test_ready_includes_scheduler_check(): void
    {
        $controller = app(HealthController::class);

        $response = $controller->ready();
        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('scheduler', $body['checks']);
        $this->assertContains($body['checks']['scheduler'], ['ok', 'stale', 'missing', 'degraded']);
    }

    public function test_ready_skips_meilisearch_when_driver_is_null(): void
    {
        config(['scout.driver' => 'null']);

        $controller = app(HealthController::class);

        $response = $controller->ready();
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame('skip', $body['checks']['meilisearch']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
