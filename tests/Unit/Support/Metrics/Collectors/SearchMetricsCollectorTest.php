<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\SearchMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Cache;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SearchMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_meili_up_and_lag(): void
    {
        Cache::forget('metrics:meili_up');
        Cache::forget('metrics:meili_lag');

        $lines = (new SearchMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_meili_up gauge', $lines);
        $this->assertContains('# TYPE nexus_meili_lag_seconds gauge', $lines);

        $up = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_meili_up ')));
        $this->assertCount(1, $up);
        $this->assertMatchesRegularExpression('/nexus_meili_up [01]/', $up[0]);

        $lag = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_meili_lag_seconds ')));
        $this->assertCount(1, $lag);
    }
}
