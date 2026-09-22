<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * SSE stream health: active gauge, admission refusals, delivered events
 * and last observed delivery lag per stream type (REL-02).
 */
final class SseMetricsCollector implements MetricsCollector
{
    private const TYPES = ['notifications', 'shoutbox'];

    private const REASONS = ['limit', 'lock'];

    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_sse_active_streams', 'Currently open SSE streams', 'gauge'),
            $this->fmt->head('nexus_sse_connects_total', 'Admitted SSE connections', 'counter'),
            $this->fmt->head('nexus_sse_rejected_total', 'Refused SSE connections by reason', 'counter'),
            $this->fmt->head('nexus_sse_events_total', 'SSE event frames sent (pings excluded)', 'counter'),
            $this->fmt->head('nexus_sse_delivery_lag_seconds', 'Age of the newest delivered item when last sent', 'gauge'),
        );

        try {
            $redis = Redis::connection();
            $active = $redis->get('shoutbox_sse_global');
            if ($active !== null) {
                $lines[] = $this->fmt->line('nexus_sse_active_streams', (int) $active);
            }

            foreach (self::TYPES as $type) {
                $connects = $redis->get('metrics:sse_connects:'.$type);
                if ($connects !== null) {
                    $lines[] = $this->fmt->line('nexus_sse_connects_total', (int) $connects, ['type' => $type]);
                }
                $events = $redis->get('metrics:sse_events:'.$type);
                if ($events !== null) {
                    $lines[] = $this->fmt->line('nexus_sse_events_total', (int) $events, ['type' => $type]);
                }
                $lag = $redis->get('metrics:sse_lag_seconds:'.$type);
                if ($lag !== null) {
                    $lines[] = $this->fmt->line('nexus_sse_delivery_lag_seconds', (int) $lag, ['type' => $type]);
                }
            }

            foreach (self::REASONS as $reason) {
                $rejected = $redis->get('metrics:sse_rejected:'.$reason);
                if ($rejected !== null) {
                    $lines[] = $this->fmt->line('nexus_sse_rejected_total', (int) $rejected, ['reason' => $reason]);
                }
            }
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }
}
