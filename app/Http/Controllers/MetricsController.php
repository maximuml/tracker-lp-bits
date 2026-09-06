<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OutboxEvent;
use App\Support\Metrics\AnnounceMetricsRecorder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;

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
 */
final class MetricsController extends Controller
{
    /** @var list<float> */
    private const LATENCY_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /**
     * Return Prometheus-format metrics.
     */
    public function index(): Response
    {
        $lines = [];

        $lines = array_merge($lines, $this->httpMetrics());
        $lines = array_merge($lines, $this->httpLatencyMetrics());
        $lines = array_merge($lines, $this->databaseMetrics());
        $lines = array_merge($lines, $this->redisMetrics());
        $lines = array_merge($lines, $this->cacheMetrics());
        $lines = array_merge($lines, $this->schedulerMetrics());
        $lines = array_merge($lines, $this->horizonMetrics());
        $lines = array_merge($lines, $this->announceMetrics());
        $lines = array_merge($lines, $this->meiliMetrics());
        $lines = array_merge($lines, $this->outboxMetrics());
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
                    $lines[] = $this->metricLine('nexus_http_requests_total', (float) $count, ['status' => $status]);
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * HTTP request latency histogram from Redis counters.
     *
     * @return list<string>
     */
    private function httpLatencyMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_http_request_duration_seconds HTTP request latency histogram';
        $lines[] = '# TYPE nexus_http_request_duration_seconds histogram';

        try {
            $redis = Redis::connection();

            foreach (self::LATENCY_BUCKETS as $bucket) {
                $count = $redis->get("metrics:http_latency_bucket:{$bucket}");
                if ($count !== null) {
                    $le = $this->formatBucket($bucket);
                    $lines[] = $this->metricLine('nexus_http_request_duration_seconds_bucket', (float) $count, ['le' => $le]);
                }
            }

            $infCount = $redis->get('metrics:http_latency_bucket:+Inf');
            if ($infCount !== null) {
                $lines[] = $this->metricLine('nexus_http_request_duration_seconds_bucket', (float) $infCount, ['le' => '+Inf']);
            }

            $sum = $redis->get('metrics:http_latency_sum');
            if ($sum !== null) {
                $lines[] = "nexus_http_request_duration_seconds_sum {$sum}";
            }

            $count = $redis->get('metrics:http_latency_count');
            if ($count !== null) {
                $lines[] = "nexus_http_request_duration_seconds_count {$count}";
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * Database connectivity and query metrics (cached for 15s).
     *
     * @return list<string>
     */
    private function databaseMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_db_up Database connectivity (1=up, 0=down)';
        $lines[] = '# TYPE nexus_db_up gauge';

        $up = Cache::remember('metrics:db_up', $this->cacheTtl(), function (): int {
            try {
                DB::connection()->getPdo();

                return 1;
            } catch (\Throwable) {
                return 0;
            }
        });

        $lines[] = "nexus_db_up {$up}";

        // Query count from Laravel's query log (if enabled)
        $lines[] = '# HELP nexus_db_query_count Total DB queries in current request';
        $lines[] = '# TYPE nexus_db_query_count gauge';
        $queryCount = DB::connection()->getQueryLog();
        $lines[] = 'nexus_db_query_count '.count($queryCount);

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
     * Cache hit/miss counters from Redis.
     *
     * @return list<string>
     */
    private function cacheMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_cache_hits_total Total cache hits';
        $lines[] = '# TYPE nexus_cache_hits_total counter';
        $lines[] = '# HELP nexus_cache_misses_total Total cache misses';
        $lines[] = '# TYPE nexus_cache_misses_total counter';

        try {
            $redis = Redis::connection();
            $hits = $redis->get('metrics:cache_hits');
            $misses = $redis->get('metrics:cache_misses');

            if ($hits !== null) {
                $lines[] = "nexus_cache_hits_total {$hits}";
            }
            if ($misses !== null) {
                $lines[] = "nexus_cache_misses_total {$misses}";
            }
        } catch (\Throwable) {
            // Skip on error
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
     * Horizon queue depth per queue and failed jobs.
     *
     * @return list<string>
     */
    private function horizonMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_horizon_pending_jobs Pending jobs per queue';
        $lines[] = '# TYPE nexus_horizon_pending_jobs gauge';
        $lines[] = '# HELP nexus_horizon_failed_jobs Total failed jobs';
        $lines[] = '# TYPE nexus_horizon_failed_jobs gauge';

        if (! class_exists(Horizon::class)) {
            return $lines;
        }

        try {
            $redis = Redis::connection();
            $queues = (array) config('metrics.queues', [
                'tracker-critical', 'default', 'nexus_queue', 'mail', 'search', 'maintenance',
            ]);

            foreach ($queues as $queue) {
                $pending = $redis->llen("queues:{$queue}:notify");
                $lines[] = $this->metricLine('nexus_horizon_pending_jobs', (float) $pending, ['queue' => $queue]);
            }

            $failed = app(JobRepository::class)->countFailed();
            $lines[] = "nexus_horizon_failed_jobs {$failed}";
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }

    /**
     * Announce rejection counters by reason category.
     *
     * @return list<string>
     */
    private function announceMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_announce_rejections_total Total announce rejections by reason';
        $lines[] = '# TYPE nexus_announce_rejections_total counter';

        try {
            $redis = Redis::connection();
            foreach (AnnounceMetricsRecorder::categories() as $reason) {
                $count = $redis->get("metrics:announce_rejections:{$reason}");
                if ($count !== null) {
                    $lines[] = $this->metricLine('nexus_announce_rejections_total', (float) $count, ['reason' => $reason]);
                }
            }
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }

    /**
     * MeiliSearch connectivity and index lag.
     *
     * @return list<string>
     */
    private function meiliMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_meili_up MeiliSearch connectivity (1=up, 0=down)';
        $lines[] = '# TYPE nexus_meili_up gauge';
        $lines[] = '# HELP nexus_meili_lag_seconds MeiliSearch indexing lag in seconds';
        $lines[] = '# TYPE nexus_meili_lag_seconds gauge';

        $up = Cache::remember('metrics:meili_up', $this->cacheTtl(), function (): int {
            try {
                $host = config('scout.meilisearch.host');
                $key = config('scout.meilisearch.key');
                $url = $host.'/health';

                $ch = curl_init($url);
                if ($ch === false) {
                    return 0;
                }
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                if ($key) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$key]);
                }
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                return $code === 200 ? 1 : 0;
            } catch (\Throwable) {
                return 0;
            }
        });

        $lines[] = "nexus_meili_up {$up}";

        // Meili lag: time since last indexing task update
        try {
            $lag = Cache::remember('metrics:meili_lag', $this->cacheTtl(), function (): int {
                $lastIndex = Redis::connection()->get('metrics:meili_last_index');
                if ($lastIndex !== null) {
                    return time() - (int) $lastIndex;
                }

                return -1;
            });
            $lines[] = "nexus_meili_lag_seconds {$lag}";
        } catch (\Throwable) {
            $lines[] = 'nexus_meili_lag_seconds -1';
        }

        return $lines;
    }

    /**
     * Outbox metrics (T-24): pending count, dead-letter count, oldest pending age, average latency.
     *
     * @return list<string>
     */
    private function outboxMetrics(): array
    {
        $lines = [];
        $lines[] = '# HELP nexus_outbox_pending_events Pending outbox events';
        $lines[] = '# TYPE nexus_outbox_pending_events gauge';
        $lines[] = '# HELP nexus_outbox_dead_letter_events Dead-lettered outbox events';
        $lines[] = '# TYPE nexus_outbox_dead_letter_events gauge';
        $lines[] = '# HELP nexus_outbox_oldest_pending_age_seconds Age of oldest pending event';
        $lines[] = '# TYPE nexus_outbox_oldest_pending_age_seconds gauge';
        $lines[] = '# HELP nexus_outbox_latency_seconds Average processing latency';
        $lines[] = '# TYPE nexus_outbox_latency_seconds gauge';

        try {
            // Single query for all outbox stats to respect query budget
            $stats = DB::table('outbox_events')
                ->selectRaw(
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dead_letter, '.
                    'MIN(CASE WHEN status = ? THEN created_at END) as oldest_pending, '.
                    'AVG(CASE WHEN status = ? AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) END) as avg_latency',
                )
                ->addBinding([
                    OutboxEvent::STATUS_PENDING,
                    OutboxEvent::STATUS_DEAD_LETTER,
                    OutboxEvent::STATUS_PENDING,
                    OutboxEvent::STATUS_COMPLETED,
                ], 'select')
                ->first();

            $pending = (int) ($stats->pending ?? 0);
            $deadLetter = (int) ($stats->dead_letter ?? 0);
            $oldest = $stats?->oldest_pending;
            $age = $oldest !== null ? abs((int) now()->diffInSeconds($oldest)) : 0;
            $avgLatency = (float) ($stats->avg_latency ?? 0);

            $lines[] = "nexus_outbox_pending_events {$pending}";
            $lines[] = "nexus_outbox_dead_letter_events {$deadLetter}";
            $lines[] = "nexus_outbox_oldest_pending_age_seconds {$age}";
            $lines[] = 'nexus_outbox_latency_seconds '.number_format($avgLatency, 6);
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
        $version = $this->escapeLabelValue((string) config('app.version', 'unknown'));
        $env = $this->escapeLabelValue((string) config('app.env', 'unknown'));
        $lines[] = "nexus_app_info{version=\"{$version}\",env=\"{$env}\"} 1";

        return $lines;
    }

    /**
     * Build a Prometheus metric line with labels.
     *
     * @param  array<string, string>  $labels
     */
    private function metricLine(string $name, float $value, array $labels = []): string
    {
        if ($labels === []) {
            return "{$name} {$value}";
        }

        $parts = [];
        foreach ($labels as $key => $val) {
            $escaped = $this->escapeLabelValue((string) $val);
            $parts[] = "{$key}=\"{$escaped}\"";
        }

        return $name.'{'.implode(',', $parts)."} {$value}";
    }

    /**
     * Escape a label value per Prometheus format (RFC 9457-style escaping).
     */
    private function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }

    /**
     * Format a bucket boundary for the le= label.
     */
    private function formatBucket(float $bucket): string
    {
        if ($bucket === (float) (int) $bucket) {
            return (string) (int) $bucket;
        }

        return (string) $bucket;
    }

    /**
     * Get the dependency check cache TTL from config.
     */
    private function cacheTtl(): int
    {
        return (int) config('metrics.dependency_cache_ttl', 15);
    }
}
