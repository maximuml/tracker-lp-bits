<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * Redis connectivity and PING latency.
 */
final class RedisMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_redis_up', 'Redis connectivity (1=up, 0=down)', 'gauge'),
            $this->fmt->head('nexus_redis_ping_seconds', 'Redis PING latency in seconds', 'gauge'),
        );

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
}
