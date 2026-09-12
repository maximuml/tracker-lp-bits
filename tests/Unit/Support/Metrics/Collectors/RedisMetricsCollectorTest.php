<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\RedisMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class RedisMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_redis_up_and_ping(): void
    {
        $lines = (new RedisMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_redis_up gauge', $lines);
        $this->assertContains('# TYPE nexus_redis_ping_seconds gauge', $lines);
        $this->assertContains('nexus_redis_up 1', $lines);

        $ping = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_redis_ping_seconds ')));
        $this->assertCount(1, $ping);
    }
}
