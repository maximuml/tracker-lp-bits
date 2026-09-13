<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\HttpMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class HttpMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_request_and_latency_families(): void
    {
        $lines = (new HttpMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# HELP nexus_http_requests_total Total HTTP requests by status code', $lines);
        $this->assertContains('# TYPE nexus_http_requests_total counter', $lines);
        $this->assertContains('# HELP nexus_http_request_duration_seconds HTTP request latency histogram', $lines);
        $this->assertContains('# TYPE nexus_http_request_duration_seconds histogram', $lines);
    }

    public function test_collect_emits_seeded_counters_and_histogram(): void
    {
        $redis = Redis::connection();
        $redis->set('metrics:http_requests:200', 11);
        $redis->set('metrics:http_latency_bucket:0.5', 4);
        $redis->set('metrics:http_latency_bucket:+Inf', 9);
        $redis->set('metrics:http_latency_sum', '1.5');
        $redis->set('metrics:http_latency_count', 9);

        $lines = (new HttpMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_http_requests_total{status="200"} 11', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_bucket{le="0.5"} 4', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_bucket{le="+Inf"} 9', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_sum 1.5', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_count 9', $lines);
    }
}
