<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;

/**
 * Horizon queue depth per queue and failed jobs.
 */
final class QueueMetricsCollector implements MetricsCollector
{
    public function __construct(
        private readonly PrometheusFormatter $fmt,
        private readonly ?JobRepository $jobs,
    ) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_horizon_pending_jobs', 'Pending jobs per queue', 'gauge'),
            $this->fmt->head('nexus_horizon_failed_jobs', 'Total failed jobs', 'gauge'),
        );

        if ($this->jobs === null) {
            return $lines;
        }

        try {
            $redis = Redis::connection();
            $queues = (array) config('metrics.queues', [
                'tracker-critical', 'default', 'nexus_queue', 'mail', 'search', 'maintenance',
            ]);

            foreach ($queues as $queue) {
                $pending = $redis->llen("queues:{$queue}:notify");
                $lines[] = $this->fmt->line('nexus_horizon_pending_jobs', (float) $pending, ['queue' => $queue]);
            }

            $lines[] = 'nexus_horizon_failed_jobs '.$this->jobs->countFailed();
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }
}
