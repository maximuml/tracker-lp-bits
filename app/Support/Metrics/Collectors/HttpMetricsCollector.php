<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * HTTP request counters and latency histogram from Redis counters.
 */
final class HttpMetricsCollector implements MetricsCollector
{
    /** @var list<float> */
    private const LATENCY_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        return array_merge($this->requests(), $this->latency(), $this->legacyAjax(), $this->legacyShims());
    }

    /**
     * @return list<string>
     */
    private function requests(): array
    {
        $lines = $this->fmt->head('nexus_http_requests_total', 'Total HTTP requests by status code', 'counter');

        try {
            $redis = Redis::connection();
            $statuses = (array) $redis->smembers('metrics:http_statuses');
            sort($statuses);
            foreach ($statuses as $status) {
                $count = $redis->get("metrics:http_requests:{$status}");
                if ($count !== null) {
                    $lines[] = $this->fmt->line('nexus_http_requests_total', (float) $count, ['status' => (string) $status]);
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function latency(): array
    {
        $lines = $this->fmt->head('nexus_http_request_duration_seconds', 'HTTP request latency histogram', 'histogram');

        try {
            $redis = Redis::connection();

            foreach (self::LATENCY_BUCKETS as $bucket) {
                $count = $redis->get("metrics:http_latency_bucket:{$bucket}") ?? 0;
                $lines[] = $this->fmt->line('nexus_http_request_duration_seconds_bucket', (float) $count, ['le' => $this->fmt->bucket($bucket)]);
            }

            $infCount = $redis->get('metrics:http_latency_bucket:+Inf') ?? 0;
            $lines[] = $this->fmt->line('nexus_http_request_duration_seconds_bucket', (float) $infCount, ['le' => '+Inf']);

            $sum = $redis->get('metrics:http_latency_sum') ?? 0;
            $lines[] = "nexus_http_request_duration_seconds_sum {$sum}";

            $count = $redis->get('metrics:http_latency_count') ?? 0;
            $lines[] = "nexus_http_request_duration_seconds_count {$count}";
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function legacyAjax(): array
    {
        $lines = $this->fmt->head('nexus_legacy_ajax_requests_total', 'Hits on the /ajax 308 shim by action', 'counter');

        try {
            $redis = Redis::connection();
            $actions = (array) $redis->smembers('metrics:legacy_ajax_actions');
            sort($actions);
            foreach ($actions as $action) {
                $count = $redis->get("metrics:legacy_ajax:{$action}");
                if ($count !== null) {
                    $lines[] = $this->fmt->line('nexus_legacy_ajax_requests_total', (float) $count, ['action' => (string) $action]);
                }
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function legacyShims(): array
    {
        $lines = $this->fmt->head('nexus_legacy_shim_hits_total', 'Hits on legacy-URI redirect shims by route URI and status', 'counter');

        try {
            $redis = Redis::connection();
            $keys = (array) $redis->smembers('metrics:legacy_shim_keys');
            sort($keys);
            foreach ($keys as $key) {
                $count = $redis->get("metrics:legacy_shim:{$key}");
                if ($count === null) {
                    continue;
                }
                $sep = strrpos((string) $key, ':');
                if ($sep === false) {
                    continue;
                }
                $uri = substr((string) $key, 0, $sep);
                $status = substr((string) $key, $sep + 1);
                $lines[] = $this->fmt->line('nexus_legacy_shim_hits_total', (float) $count, ['uri' => $uri, 'status' => $status]);
            }
        } catch (\Throwable) {
            // Redis unavailable — skip
        }

        return $lines;
    }
}
