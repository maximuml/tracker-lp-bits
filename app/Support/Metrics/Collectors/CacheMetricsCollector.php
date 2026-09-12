<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * Cache hit/miss counters from Redis.
 */
final class CacheMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_cache_hits_total', 'Total cache hits', 'counter'),
            $this->fmt->head('nexus_cache_misses_total', 'Total cache misses', 'counter'),
        );

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
}
