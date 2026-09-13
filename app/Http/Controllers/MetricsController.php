<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Metrics\MetricsRegistry;
use Illuminate\Http\Response;

/**
 * Prometheus-compatible /metrics endpoint for observability (T-23).
 *
 * Exposes application metrics in the Prometheus text exposition format:
 * - nexus_http_requests_total (counter, by status_code)
 * - nexus_http_request_duration_seconds (histogram)
 * - nexus_db_up / nexus_db_query_count (gauge)
 * - nexus_redis_up / nexus_redis_ping_seconds (gauge)
 * - nexus_cache_hits_total / nexus_cache_misses_total (counter)
 * - nexus_scheduler_heartbeat_age_seconds / nexus_scheduler_up (gauge)
 * - nexus_horizon_pending_jobs / nexus_horizon_failed_jobs (gauge, per queue)
 * - nexus_announce_rejections_total (counter, by reason)
 * - nexus_meili_up / nexus_meili_lag_seconds (gauge)
 * - nexus_outbox_pending_events / nexus_outbox_dead_letter_events (gauge)
 * - nexus_outbox_oldest_pending_age_seconds / nexus_outbox_latency_seconds (gauge)
 * - nexus_app_info (gauge with labels)
 *
 * Access is controlled by the MetricsAccess middleware (internal network
 * or bearer token). Dependency checks are cached for 15 seconds.
 *
 * W6-01: the controller only orchestrates — each domain lives in a
 * MetricsCollector (app/Support/Metrics/Collectors). A throwing collector
 * is skipped so one broken dependency cannot take down the endpoint.
 */
final class MetricsController extends Controller
{
    public function __construct(private readonly MetricsRegistry $registry) {}

    /**
     * Return Prometheus-format metrics.
     */
    public function index(): Response
    {
        $lines = [];

        foreach ($this->registry->all() as $collector) {
            try {
                $lines = array_merge($lines, $collector->collect());
            } catch (\Throwable) {
                // Collector isolation: skip the broken domain, keep serving.
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
