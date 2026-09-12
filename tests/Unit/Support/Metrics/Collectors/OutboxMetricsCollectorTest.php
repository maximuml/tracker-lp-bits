<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\OutboxMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OutboxMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_all_outbox_gauges(): void
    {
        $lines = (new OutboxMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_outbox_pending_events gauge', $lines);
        $this->assertContains('# TYPE nexus_outbox_dead_letter_events gauge', $lines);
        $this->assertContains('# TYPE nexus_outbox_oldest_pending_age_seconds gauge', $lines);
        $this->assertContains('# TYPE nexus_outbox_latency_seconds gauge', $lines);

        foreach (['nexus_outbox_pending_events', 'nexus_outbox_dead_letter_events', 'nexus_outbox_oldest_pending_age_seconds', 'nexus_outbox_latency_seconds'] as $name) {
            $this->assertNotEmpty(
                array_filter($lines, static fn (string $l) => str_starts_with($l, $name.' ')),
                "missing {$name} sample line",
            );
        }
    }
}
