<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\QueueMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class QueueMetricsCollectorTest extends TestCase
{
    public function test_collect_without_jobs_repository_emits_headers_only(): void
    {
        $lines = (new QueueMetricsCollector(new PrometheusFormatter, null))->collect();

        $this->assertContains('# TYPE nexus_horizon_pending_jobs gauge', $lines);
        $this->assertContains('# TYPE nexus_horizon_failed_jobs gauge', $lines);
        $this->assertCount(4, $lines);
    }

    public function test_collect_emits_queue_depth_and_failed_jobs(): void
    {
        Redis::connection()->del('queues:default:notify');
        Redis::connection()->rpush('queues:default:notify', 'a', 'b');

        $jobs = $this->createStub(JobRepository::class);
        $jobs->method('countFailed')->willReturn(3);

        $lines = (new QueueMetricsCollector(new PrometheusFormatter, $jobs))->collect();

        $this->assertContains('nexus_horizon_pending_jobs{queue="default"} 2', $lines);
        $this->assertContains('nexus_horizon_failed_jobs 3', $lines);
    }
}
