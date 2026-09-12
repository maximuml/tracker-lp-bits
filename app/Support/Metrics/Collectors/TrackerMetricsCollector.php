<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\AnnounceMetricsRecorder;
use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * Announce rejection counters by reason category.
 */
final class TrackerMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = $this->fmt->head('nexus_announce_rejections_total', 'Total announce rejections by reason', 'counter');

        try {
            $redis = Redis::connection();
            foreach (AnnounceMetricsRecorder::categories() as $reason) {
                $count = $redis->get("metrics:announce_rejections:{$reason}");
                if ($count !== null) {
                    $lines[] = $this->fmt->line('nexus_announce_rejections_total', (float) $count, ['reason' => $reason]);
                }
            }
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }
}
