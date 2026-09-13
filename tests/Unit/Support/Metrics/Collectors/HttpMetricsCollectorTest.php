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
        $redis->sadd('metrics:http_statuses', '200');
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

    public function test_collect_emits_statuses_recorded_outside_fixed_list(): void
    {
        $redis = Redis::connection();
        $redis->set('metrics:http_requests:418', 3);
        $redis->sadd('metrics:http_statuses', '418');

        $lines = (new HttpMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_http_requests_total{status="418"} 3', $lines);
    }

    public function test_collect_emits_zero_buckets_for_complete_histogram(): void
    {
        $redis = Redis::connection();
        foreach (['0.005', '0.01', '0.025', '0.05', '0.1', '0.25', '0.5', '1', '2.5', '5', '10', '+Inf'] as $b) {
            $redis->del("metrics:http_latency_bucket:{$b}");
        }
        $redis->del('metrics:http_latency_sum', 'metrics:http_latency_count');

        $lines = (new HttpMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('nexus_http_request_duration_seconds_bucket{le="0.005"} 0', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_bucket{le="10"} 0', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_bucket{le="+Inf"} 0', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_sum 0', $lines);
        $this->assertContains('nexus_http_request_duration_seconds_count 0', $lines);
    }
}
