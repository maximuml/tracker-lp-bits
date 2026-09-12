<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\AnnounceMetricsRecorder;
use App\Support\Metrics\Collectors\TrackerMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TrackerMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_announce_rejection_categories(): void
    {
        AnnounceMetricsRecorder::recordRejection('Invalid passkey!');

        $lines = (new TrackerMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# TYPE nexus_announce_rejections_total counter', $lines);

        $reasons = array_filter($lines, static fn (string $l) => str_contains($l, 'reason="invalid_passkey"'));
        $this->assertNotEmpty($reasons);
    }
}
