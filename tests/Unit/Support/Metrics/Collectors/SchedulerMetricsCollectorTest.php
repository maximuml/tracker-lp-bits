<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\SchedulerMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SchedulerMetricsCollectorTest extends TestCase
{
    public function test_collect_reports_fresh_heartbeat_as_up(): void
    {
        Redis::connection()->set('scheduler:heartbeat', time());

        $lines = (new SchedulerMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_scheduler_up 1', $lines);
        $age = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_scheduler_heartbeat_age_seconds ')));
        $this->assertCount(1, $age);
        $this->assertMatchesRegularExpression('/nexus_scheduler_heartbeat_age_seconds \d+/', $age[0]);
    }

    public function test_collect_reports_missing_heartbeat_as_down(): void
    {
        Redis::connection()->del('scheduler:heartbeat');

        $lines = (new SchedulerMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_scheduler_heartbeat_age_seconds -1', $lines);
        $this->assertContains('nexus_scheduler_up 0', $lines);
    }

    public function test_collect_reports_stale_heartbeat_as_down(): void
    {
        Redis::connection()->set('scheduler:heartbeat', time() - 600);

        $lines = (new SchedulerMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_scheduler_up 0', $lines);
    }
}
