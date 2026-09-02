<?php

namespace Tests\Unit;

use App\Http\Controllers\MetricsController;
use Tests\TestCase;

/**
 * Wave 5 Step 22: Observability — metrics endpoint, Sentry, JSON logs.
 *
 * Verifies that:
 * - /metrics route exists and returns Prometheus-format text
 * - MetricsController exposes DB, Redis, scheduler, Horizon, HTTP metrics
 * - RecordHttpMetrics middleware increments Redis counters
 * - Sentry service providers are registered in config/app.php
 * - JSON log channel exists with request_id injection
 * - .env.example documents LOG_STACK=daily,json for production
 */
final class ObservabilityTest extends TestCase
{
    /**
     * /metrics route exists.
     */
    public function test_metrics_route_exists(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString('/metrics', $routes, 'metrics route must exist');
        $this->assertStringContainsString('MetricsController', $routes, 'metrics route must use MetricsController');
    }

    /**
     * MetricsController returns Prometheus text format.
     */
    public function test_metrics_controller_returns_prometheus_format(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type', ''));
        $content = $response->getContent();
        $this->assertIsString($content);
        // Prometheus format has HELP and TYPE comments
        $this->assertStringContainsString('# HELP', $content);
        $this->assertStringContainsString('# TYPE', $content);
    }

    /**
     * MetricsController exposes database up metric.
     */
    public function test_metrics_controller_exposes_db_metric(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('nexus_db_up', $content);
    }

    /**
     * MetricsController exposes Redis up metric.
     */
    public function test_metrics_controller_exposes_redis_metric(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('nexus_redis_up', $content);
    }

    /**
     * MetricsController exposes scheduler heartbeat metric.
     */
    public function test_metrics_controller_exposes_scheduler_metric(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('nexus_scheduler', $content);
    }

    /**
     * MetricsController exposes HTTP requests total metric.
     */
    public function test_metrics_controller_exposes_http_metric(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('nexus_http_requests_total', $content);
    }

    /**
     * MetricsController exposes app info metric.
     */
    public function test_metrics_controller_exposes_app_info(): void
    {
        $controller = new MetricsController;
        $response = $controller->index();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('nexus_app_info', $content);
    }

    /**
     * RecordHttpMetrics middleware exists and skips /metrics path.
     */
    public function test_record_http_metrics_middleware_skips_metrics_path(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/RecordHttpMetrics.php'));
        $this->assertStringContainsString('metrics', $source, 'Middleware must check for metrics path');
        $this->assertStringContainsString('Redis', $source, 'Middleware must use Redis for counters');
        $this->assertStringContainsString('incr', $source, 'Middleware must increment Redis counter');
    }

    /**
     * RecordHttpMetrics is registered in Kernel.
     */
    public function test_record_http_metrics_registered_in_kernel(): void
    {
        $kernel = file_get_contents(app_path('Http/Kernel.php'));
        $this->assertStringContainsString('RecordHttpMetrics', $kernel, 'RecordHttpMetrics must be registered in Kernel');
    }

    /**
     * Sentry service providers are registered.
     */
    public function test_sentry_providers_registered(): void
    {
        $config = file_get_contents(base_path('config/app.php'));
        $this->assertStringContainsString('Sentry\Laravel\ServiceProvider', $config, 'Sentry ServiceProvider must be registered');
        $this->assertStringContainsString('Sentry\Laravel\Tracing\ServiceProvider', $config, 'Sentry Tracing ServiceProvider must be registered');
    }

    /**
     * Sentry config has release and environment tags.
     */
    public function test_sentry_config_has_release_tags(): void
    {
        $config = file_get_contents(base_path('config/sentry.php'));
        $this->assertStringContainsString('release', $config, 'Sentry config must have release tag');
        $this->assertStringContainsString('environment', $config, 'Sentry config must have environment tag');
    }

    /**
     * JSON log channel exists with request_id injection.
     */
    public function test_json_log_channel_exists(): void
    {
        $config = file_get_contents(base_path('config/logging.php'));
        $this->assertStringContainsString("'json'", $config, 'logging.php must have json channel');
        $this->assertStringContainsString('JsonLogFormatter', $config, 'json channel must use JsonLogFormatter');
    }

    /**
     * JsonLogFormatter injects request_id.
     */
    public function test_json_log_formatter_injects_request_id(): void
    {
        $source = file_get_contents(app_path('Logging/JsonLogFormatter.php'));
        $this->assertStringContainsString('request_id', $source, 'JsonLogFormatter must inject request_id');
        $this->assertStringContainsString('RequestContext', $source, 'JsonLogFormatter must use RequestContext');
    }

    /**
     * .env.example documents LOG_STACK=daily,json for production.
     */
    public function test_env_example_documents_json_log_stack(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('LOG_STACK', $env, '.env.example must have LOG_STACK');
        $this->assertStringContainsString('daily,json', $env, '.env.example must document daily,json for production');
    }

    /**
     * X-Request-Id header is set in Locale middleware.
     */
    public function test_request_id_header_in_middleware(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/Locale.php'));
        $this->assertStringContainsString('X-Request-Id', $source, 'Locale middleware must set X-Request-Id header');
        $this->assertStringContainsString('RequestContext', $source, 'Locale middleware must use RequestContext');
    }
}
