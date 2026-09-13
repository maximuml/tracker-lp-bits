<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\CacheMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class CacheMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_cache_counters(): void
    {
        Redis::connection()->set('metrics:cache_hits', 7);
        Redis::connection()->set('metrics:cache_misses', 3);

        $lines = (new CacheMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_cache_hits_total counter', $lines);
        $this->assertContains('# TYPE nexus_cache_misses_total counter', $lines);
        $this->assertContains('nexus_cache_hits_total 7', $lines);
        $this->assertContains('nexus_cache_misses_total 3', $lines);
    }
}
