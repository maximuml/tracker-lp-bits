<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Repositories\OutboxEventRepository;
use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;

/**
 * Outbox metrics (T-24): pending, dead-letter, oldest pending age, avg latency.
 */
final class OutboxMetricsCollector implements MetricsCollector
{
    public function __construct(
        private readonly PrometheusFormatter $fmt,
        private readonly OutboxEventRepository $outboxEventRepository,
    ) {}

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
            $stats = $this->outboxEventRepository->collectStatusStats();

            $pending = (int) ($stats['pending'] ?? 0);
            $deadLetter = (int) ($stats['dead_letter'] ?? 0);
            $oldest = $stats['oldest_pending'] ?? null;
            $age = $oldest !== null ? abs((int) now()->diffInSeconds($oldest)) : 0;
            $avgLatency = (float) ($stats['avg_latency'] ?? 0);

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
