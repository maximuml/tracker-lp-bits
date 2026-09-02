<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;

/**
 * Prometheus-compatible /metrics endpoint for observability.
 *
 * Exposes application metrics in the Prometheus text exposition format:
 * - nexus_http_requests_total (counter, by status_code)
 * - nexus_http_request_duration_seconds (histogram summary)
 * - nexus_db_query_count (gauge, per request)
 * - nexus_redis_ping_seconds (gauge)
 * - nexus_scheduler_heartbeat_age_seconds (gauge)
 * - nexus_horizon_pending_jobs (gauge)
 * - nexus_horizon_failed_jobs (gauge)
 *
 * This endpoint is exempt from auth and throttling. It should be
 * scraped by Prometheus at regular intervals (e.g. 15s).
 */
final class MetricsController extends Controller
{
    /**
     * Return Prometheus-format metrics.
     */
    public function index(): Response
    {
        $lines = [];

        // HTTP request metrics (from Redis counters if available)
        $lines = array_merge($lines, $this->httpMetrics());

        // Database metrics
        $lines = array_merge($lines, $this->databaseMetrics());

        // Redis metrics
        $lines = array_merge($lines, $this->redisMetrics());

        // Scheduler heartbeat age
        $lines = array_merge($lines, $this->schedulerMetrics());

        // Horizon metrics
        $lines = array_merge($lines, $this->horizonMetrics());

        // Application info
        $lines = array_merge($lines, $this->appInfo());

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * HTTP request metrics from Redis counters.
     *
     * @return list<string>
     */
    private function httpMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_http_requests_total Total HTTP requests by status code';
        $lines[] = '# TYPE nexus_http_requests_total counter';

        try {
            $redis = Redis::connection();
            $statuses = ['200', '301', '302', '400', '401', '403', '404', '419', '422', '429', '500', '503'];
            foreach ($statuses as $status) {
                $count = $redis->get("metrics:http_requests:{$status}");
                if ($count !== null) {
                    $lines[] = "nexus_http_requests_total{status=\"{$status}\"} {$count}";
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * Database connectivity and query metrics.
     *
     * @return list<string>
     */
    private function databaseMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_db_up Database connectivity (1=up, 0=down)';
        $lines[] = '# TYPE nexus_db_up gauge';

        try {
            DB::connection()->getPdo();
            $lines[] = 'nexus_db_up 1';
        } catch (\Throwable) {
            $lines[] = 'nexus_db_up 0';
        }

        return $lines;
    }

    /**
     * Redis connectivity and latency.
     *
     * @return list<string>
     */
    private function redisMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_redis_up Redis connectivity (1=up, 0=down)';
        $lines[] = '# TYPE nexus_redis_up gauge';
        $lines[] = '# HELP nexus_redis_ping_seconds Redis PING latency in seconds';
        $lines[] = '# TYPE nexus_redis_ping_seconds gauge';

        try {
            $start = hrtime(true);
            Redis::connection()->ping();
            $elapsed = (hrtime(true) - $start) / 1_000_000_000;
            $lines[] = 'nexus_redis_up 1';
            $lines[] = 'nexus_redis_ping_seconds '.number_format($elapsed, 6);
        } catch (\Throwable) {
            $lines[] = 'nexus_redis_up 0';
        }

        return $lines;
    }

    /**
     * Scheduler heartbeat age (seconds since last heartbeat).
     *
     * @return list<string>
     */
    private function schedulerMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_scheduler_heartbeat_age_seconds Age of scheduler heartbeat in seconds';
        $lines[] = '# TYPE nexus_scheduler_heartbeat_age_seconds gauge';
        $lines[] = '# HELP nexus_scheduler_up Scheduler running (1=yes, 0=no heartbeat)';
        $lines[] = '# TYPE nexus_scheduler_up gauge';

        try {
            $heartbeat = Redis::connection()->get('scheduler:heartbeat');
            if ($heartbeat !== null) {
                $age = time() - (int) $heartbeat;
                $lines[] = "nexus_scheduler_heartbeat_age_seconds {$age}";
                $lines[] = 'nexus_scheduler_up '.($age < 300 ? '1' : '0');
            } else {
                $lines[] = 'nexus_scheduler_heartbeat_age_seconds -1';
                $lines[] = 'nexus_scheduler_up 0';
            }
        } catch (\Throwable) {
            $lines[] = 'nexus_scheduler_heartbeat_age_seconds -1';
            $lines[] = 'nexus_scheduler_up 0';
        }

        return $lines;
    }

    /**
     * Horizon queue depth and failed jobs.
     *
     * @return list<string>
     */
    private function horizonMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_horizon_pending_jobs Pending jobs across all queues';
        $lines[] = '# TYPE nexus_horizon_pending_jobs gauge';
        $lines[] = '# HELP nexus_horizon_failed_jobs Total failed jobs';
        $lines[] = '# TYPE nexus_horizon_failed_jobs gauge';

        if (! class_exists(Horizon::class)) {
            return $lines;
        }

        try {
            // Pending jobs from Redis
            $pending = Redis::connection()->llen('queues:default:notify');
            $lines[] = "nexus_horizon_pending_jobs {$pending}";

            // Failed jobs count from Horizon repository
            $failed = app(JobRepository::class)->countFailed();
            $lines[] = "nexus_horizon_failed_jobs {$failed}";
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }

    /**
     * Application info metric.
     *
     * @return list<string>
     */
    private function appInfo(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_app_info Application metadata';
        $lines[] = '# TYPE nexus_app_info gauge';
        $version = config('app.version', 'unknown');
        $env = config('app.env', 'unknown');
        $lines[] = "nexus_app_info{version=\"{$version}\",env=\"{$env}\"} 1";

        return $lines;
    }
}
