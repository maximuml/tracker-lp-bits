<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;

/**
 * Scheduler heartbeat age (seconds since last heartbeat).
 */
final class SchedulerMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_scheduler_heartbeat_age_seconds', 'Age of scheduler heartbeat in seconds', 'gauge'),
            $this->fmt->head('nexus_scheduler_up', 'Scheduler running (1=yes, 0=no heartbeat)', 'gauge'),
        );

        try {
            $heartbeat = Redis::connection()->get('scheduler:heartbeat');
            if ($heartbeat !== null) {
                $age = time() - (int) $heartbeat;
                $lines[] = "nexus_scheduler_heartbeat_age_seconds {$age}";
                $lines[] = 'nexus_scheduler_up '.($age < 300 ? '1' : '0');
            } else {
                $lines[] = 'nexus_scheduler_heartbeat_age_seconds -1';
                $lines[] = 'nexus_scheduler_up 0';
            }
        } catch (\Throwable) {
            $lines[] = 'nexus_scheduler_heartbeat_age_seconds -1';
            $lines[] = 'nexus_scheduler_up 0';
        }

        return $lines;
    }
}
