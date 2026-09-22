<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\SseMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SseMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_sse_metrics(): void
    {
        $redis = Redis::connection();
        $redis->set('shoutbox_sse_global', 3);
        $redis->set('metrics:sse_connects:notifications', 12);
        $redis->set('metrics:sse_events:shoutbox', 4);
        $redis->set('metrics:sse_rejected:lock', 2);
        $redis->set('metrics:sse_lag_seconds:notifications', 1);

        $lines = (new SseMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_sse_active_streams gauge', $lines);
        $this->assertContains('nexus_sse_active_streams 3', $lines);
        $this->assertContains('nexus_sse_connects_total{type="notifications"} 12', $lines);
        $this->assertContains('nexus_sse_events_total{type="shoutbox"} 4', $lines);
        $this->assertContains('nexus_sse_rejected_total{reason="lock"} 2', $lines);
        $this->assertContains('nexus_sse_delivery_lag_seconds{type="notifications"} 1', $lines);

        foreach ([
            'shoutbox_sse_global',
            'metrics:sse_connects:notifications', 'metrics:sse_events:shoutbox',
            'metrics:sse_rejected:lock', 'metrics:sse_lag_seconds:notifications',
        ] as $key) {
            $redis->del($key);
        }
    }

    public function test_collect_skips_absent_keys(): void
    {
        $redis = Redis::connection();
        foreach ([
            'shoutbox_sse_global',
            'metrics:sse_connects:notifications', 'metrics:sse_connects:shoutbox',
            'metrics:sse_events:notifications', 'metrics:sse_events:shoutbox',
            'metrics:sse_rejected:limit', 'metrics:sse_rejected:lock',
            'metrics:sse_lag_seconds:notifications', 'metrics:sse_lag_seconds:shoutbox',
        ] as $key) {
            $redis->del($key);
        }

        $lines = (new SseMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_sse_active_streams gauge', $lines);
        $this->assertCount(10, $lines); // five HELP+TYPE heads, no samples
    }
}
