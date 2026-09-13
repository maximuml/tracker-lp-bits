<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Database connectivity and query metrics (connectivity cached briefly).
 */
final class DatabaseMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = $this->fmt->head('nexus_db_up', 'Database connectivity (1=up, 0=down)', 'gauge');

        $up = Cache::remember('metrics:db_up', $this->cacheTtl(), function (): int {
            try {
                DB::connection()->getPdo();

                return 1;
            } catch (\Throwable) {
                return 0;
            }
        });

        $lines[] = "nexus_db_up {$up}";

        $lines = array_merge($lines, $this->fmt->head('nexus_db_query_count', 'Total DB queries in current request', 'gauge'));
        $lines[] = 'nexus_db_query_count '.RequestContext::instance()->getDbQueryCount();

        return $lines;
    }

    private function cacheTtl(): int
    {
        return (int) config('metrics.dependency_cache_ttl', 15);
    }
}
