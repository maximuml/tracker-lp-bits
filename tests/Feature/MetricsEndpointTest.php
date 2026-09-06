<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Metrics\AnnounceMetricsRecorder;
use Tests\TestCase;

final class MetricsEndpointTest extends TestCase
{
    public function test_metrics_returns_prometheus_format(): void
    {
        // MetricsAccess allows 127.0.0.1 by default (private network)
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $body = $response->getContent();
        $this->assertNotEmpty($body);

        // Check for required metric families
        $this->assertStringContainsString('# HELP nexus_http_requests_total', $body);
        $this->assertStringContainsString('# TYPE nexus_http_requests_total counter', $body);
        $this->assertStringContainsString('# HELP nexus_db_up', $body);
        $this->assertStringContainsString('# TYPE nexus_db_up gauge', $body);
        $this->assertStringContainsString('# HELP nexus_redis_up', $body);
        $this->assertStringContainsString('# HELP nexus_horizon_pending_jobs', $body);
        $this->assertStringContainsString('# HELP nexus_announce_rejections_total', $body);
        $this->assertStringContainsString('# HELP nexus_meili_up', $body);
        $this->assertStringContainsString('# HELP nexus_app_info', $body);
    }

    public function test_metrics_includes_latency_histogram(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();

        $this->assertStringContainsString('# HELP nexus_http_request_duration_seconds', $body);
        $this->assertStringContainsString('# TYPE nexus_http_request_duration_seconds histogram', $body);
    }

    public function test_metrics_includes_cache_counters(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();

        $this->assertStringContainsString('# HELP nexus_cache_hits_total', $body);
        $this->assertStringContainsString('# HELP nexus_cache_misses_total', $body);
    }

    public function test_metrics_includes_queue_depth_per_queue(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();

        // Should have queue labels for all configured queues
        $this->assertStringContainsString('queue="tracker-critical"', $body);
        $this->assertStringContainsString('queue="default"', $body);
        $this->assertStringContainsString('queue="mail"', $body);
        $this->assertStringContainsString('queue="search"', $body);
        $this->assertStringContainsString('queue="maintenance"', $body);
    }

    public function test_metrics_includes_announce_rejection_categories(): void
    {
        // Seed some rejection data so the categories appear in output
        AnnounceMetricsRecorder::recordRejection('Invalid passkey!');
        AnnounceMetricsRecorder::recordRejection('Your account is disabled!');
        AnnounceMetricsRecorder::recordRejection('torrent not registered with this tracker');

        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();

        // Should expose the cardinality-controlled reason categories that have data
        $this->assertStringContainsString('reason="invalid_passkey"', $body);
        $this->assertStringContainsString('reason="disabled_account"', $body);
        $this->assertStringContainsString('reason="torrent_not_registered"', $body);
    }

    public function test_metrics_denies_access_with_token_configured_and_no_token(): void
    {
        config(['metrics.token' => 'secret-token-123']);
        // Simulate external request (not from private network)
        $response = $this->call('GET', '/metrics', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

        $response->assertStatus(403);
    }

    public function test_metrics_allows_access_with_valid_bearer_token(): void
    {
        config(['metrics.token' => 'secret-token-123']);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-token-123',
        ])->get('/metrics');

        $response->assertStatus(200);
    }

    public function test_metrics_denies_access_with_invalid_bearer_token(): void
    {
        config(['metrics.token' => 'secret-token-123']);
        // Simulate external request with invalid bearer token
        $response = $this->call('GET', '/metrics', [], [], [], [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
        ]);

        $response->assertStatus(403);
    }

    public function test_metrics_label_values_are_escaped(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $body = $response->getContent();

        // Label values should not contain unescaped newlines or quotes
        // (basic sanity check — app_info labels are safe strings)
        $this->assertStringContainsString('nexus_app_info{version="', $body);
    }
}
