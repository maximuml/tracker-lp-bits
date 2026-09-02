<?php

namespace Tests\Unit;

use App\Http\Controllers\HealthController;
use Tests\TestCase;

/**
 * Wave 5 Step 19: Health and readiness endpoints.
 *
 * Verifies that:
 * - /health/live always returns 200
 * - /health/ready checks DB, Redis, MeiliSearch, Horizon, scheduler
 * - Scheduler heartbeat is written to Redis by Console\Kernel
 * - Scheduler healthcheck in docker-compose uses process check, not file check
 * - OpenResty healthcheck uses /health/live
 */
final class HealthReadinessTest extends TestCase
{
    /**
     * /health/live route exists and is exempt from auth.
     */
    public function test_health_live_route_exists(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString('health/live', $routes, 'health/live route must exist');
        $this->assertStringContainsString('health/ready', $routes, 'health/ready route must exist');
    }

    /**
     * HealthController has live() and ready() methods.
     */
    public function test_health_controller_has_methods(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('function live()', $controller, 'HealthController must have live() method');
        $this->assertStringContainsString('function ready()', $controller, 'HealthController must have ready() method');
    }

    /**
     * HealthController checks database connectivity.
     */
    public function test_health_controller_checks_database(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('database', $controller, 'HealthController must check database');
        $this->assertStringContainsString('getPdo', $controller, 'HealthController must check DB via getPdo');
    }

    /**
     * HealthController checks Redis connectivity.
     */
    public function test_health_controller_checks_redis(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('redis', $controller, 'HealthController must check redis');
        $this->assertStringContainsString('ping', $controller, 'HealthController must ping Redis');
    }

    /**
     * HealthController checks MeiliSearch (with degraded fallback).
     */
    public function test_health_controller_checks_meilisearch(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('meilisearch', $controller, 'HealthController must check MeiliSearch');
        $this->assertStringContainsString('degraded', $controller, 'HealthController must report degraded status');
    }

    /**
     * HealthController checks Horizon master supervisor status.
     */
    public function test_health_controller_checks_horizon(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('horizon', $controller, 'HealthController must check Horizon');
        $this->assertStringContainsString('MasterSupervisor', $controller, 'HealthController must check Horizon master supervisors');
    }

    /**
     * HealthController checks scheduler heartbeat from Redis.
     */
    public function test_health_controller_checks_scheduler_heartbeat(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('scheduler', $controller, 'HealthController must check scheduler');
        $this->assertStringContainsString('heartbeat', $controller, 'HealthController must check scheduler heartbeat');
        $this->assertStringContainsString('scheduler:heartbeat', $controller, 'HealthController must read scheduler:heartbeat key from Redis');
    }

    /**
     * HealthController includes warnings in the response.
     */
    public function test_health_controller_includes_warnings(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HealthController.php'));
        $this->assertStringContainsString('warnings', $controller, 'HealthController must include warnings in response');
    }

    /**
     * Console Kernel writes scheduler heartbeat to Redis.
     */
    public function test_console_kernel_writes_scheduler_heartbeat(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString('scheduler:heartbeat', $kernel, 'Console Kernel must write scheduler:heartbeat to Redis');
        $this->assertStringContainsString('everyMinute', $kernel, 'Console Kernel must write heartbeat every minute');
    }

    /**
     * Scheduler healthcheck in docker-compose uses process check, not file check.
     */
    public function test_scheduler_healthcheck_uses_process_check(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        // The old healthcheck checked for .env and vendor/autoload.php files.
        // The new one should check if the scheduler process is actually running.
        $this->assertStringNotContainsString(
            'test -f /var/www/html/.env && test -f /var/www/html/vendor/autoload.php',
            $compose,
            'Scheduler healthcheck must not check for file existence (use process check instead)'
        );
    }

    /**
     * OpenResty healthcheck uses /health/live endpoint.
     */
    public function test_openresty_healthcheck_uses_health_live(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));
        $this->assertStringContainsString('/health/live', $compose, 'OpenResty healthcheck must use /health/live');
    }

    /**
     * /health/live returns 200 without auth.
     */
    public function test_health_live_returns_200(): void
    {
        // Test the controller directly (unit test, not feature test)
        $controller = new HealthController;
        $response = $controller->live();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('ok', $data['status']);
    }

    /**
     * /health/ready returns JSON with checks.
     */
    public function test_health_ready_returns_checks(): void
    {
        // Test the controller directly (unit test, not feature test)
        $controller = new HealthController;
        $response = $controller->ready();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('redis', $data['checks']);
    }
}
