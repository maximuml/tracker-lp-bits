<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * MeiliSearch connectivity (2s curl timeout) and index lag.
 */
final class SearchMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_meili_up', 'MeiliSearch connectivity (1=up, 0=down)', 'gauge'),
            $this->fmt->head('nexus_meili_lag_seconds', 'MeiliSearch indexing lag in seconds', 'gauge'),
            $this->fmt->head('nexus_meili_rebuild_duration_seconds', 'Duration of the last MeiliSearch rebuild', 'gauge'),
            $this->fmt->head('nexus_meili_rebuild_documents', 'Documents imported by the last successful rebuild', 'gauge'),
            $this->fmt->head('nexus_meili_rebuild_failed_total', 'Total failed rebuild attempts', 'counter'),
            $this->fmt->head('nexus_meili_rebuild_last_success_timestamp', 'Unix time of the last successful rebuild', 'gauge'),
        );

        $lines[] = 'nexus_meili_up '.Cache::remember('metrics:meili_up', $this->cacheTtl(), $this->probe(...));

        try {
            $lag = Cache::remember('metrics:meili_lag', $this->cacheTtl(), function (): int {
                $lastIndex = Redis::connection()->get('metrics:meili_last_index');

                return $lastIndex !== null ? time() - (int) $lastIndex : -1;
            });
            $lines[] = "nexus_meili_lag_seconds {$lag}";
        } catch (\Throwable) {
            $lines[] = 'nexus_meili_lag_seconds -1';
        }

        try {
            $redis = Redis::connection();
            $lines[] = 'nexus_meili_rebuild_duration_seconds '.(float) ($redis->get('metrics:meili_rebuild_duration') ?? 0);
            $lines[] = 'nexus_meili_rebuild_documents '.(int) ($redis->get('metrics:meili_rebuild_documents') ?? 0);
            $lines[] = 'nexus_meili_rebuild_failed_total '.(int) ($redis->get('metrics:meili_rebuild_failed') ?? 0);
            $lines[] = 'nexus_meili_rebuild_last_success_timestamp '.(int) ($redis->get('metrics:meili_rebuild_last_success') ?? 0);
        } catch (\Throwable) {
            $lines[] = 'nexus_meili_rebuild_duration_seconds 0';
            $lines[] = 'nexus_meili_rebuild_documents 0';
            $lines[] = 'nexus_meili_rebuild_failed_total 0';
            $lines[] = 'nexus_meili_rebuild_last_success_timestamp 0';
        }

        return $lines;
    }

    private function probe(): int
    {
        try {
            $host = config('scout.meilisearch.host');
            $key = config('scout.meilisearch.key');
            $ch = curl_init($host.'/health');
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
    }

    private function cacheTtl(): int
    {
        return (int) config('metrics.dependency_cache_ttl', 15);
    }
}
