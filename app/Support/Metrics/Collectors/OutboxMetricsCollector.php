<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Models\OutboxEvent;
use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Outbox metrics (T-24): pending, dead-letter, oldest pending age, avg latency.
 */
final class OutboxMetricsCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = array_merge(
            $this->fmt->head('nexus_outbox_pending_events', 'Pending outbox events', 'gauge'),
            $this->fmt->head('nexus_outbox_dead_letter_events', 'Dead-lettered outbox events', 'gauge'),
            $this->fmt->head('nexus_outbox_oldest_pending_age_seconds', 'Age of oldest pending event', 'gauge'),
            $this->fmt->head('nexus_outbox_latency_seconds', 'Average processing latency', 'gauge'),
        );

        try {
            // Single query for all outbox stats to respect query budget
            $stats = DB::table('outbox_events')
                ->selectRaw(
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dead_letter, '.
                    'MIN(CASE WHEN status = ? THEN created_at END) as oldest_pending, '.
                    'AVG(CASE WHEN status = ? AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) END) as avg_latency',
                )
                ->addBinding([
                    OutboxEvent::STATUS_PENDING,
                    OutboxEvent::STATUS_DEAD_LETTER,
                    OutboxEvent::STATUS_PENDING,
                    OutboxEvent::STATUS_COMPLETED,
                ], 'select')
                ->first();

            $pending = (int) ($stats->pending ?? 0);
            $deadLetter = (int) ($stats->dead_letter ?? 0);
            $oldest = $stats?->oldest_pending;
            $age = $oldest !== null ? abs((int) now()->diffInSeconds($oldest)) : 0;
            $avgLatency = (float) ($stats->avg_latency ?? 0);

            $lines[] = "nexus_outbox_pending_events {$pending}";
            $lines[] = "nexus_outbox_dead_letter_events {$deadLetter}";
            $lines[] = "nexus_outbox_oldest_pending_age_seconds {$age}";
            $lines[] = 'nexus_outbox_latency_seconds '.number_format($avgLatency, 6);
        } catch (\Throwable) {
            // Skip on error
        }

        return $lines;
    }
}
