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
        return array_merge($this->requests(), $this->latency());
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
}
