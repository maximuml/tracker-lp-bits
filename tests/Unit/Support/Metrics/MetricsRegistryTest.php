<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\MetricsRegistry;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W6-01: the container must resolve the full collector list.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MetricsRegistryTest extends TestCase
{
    public function test_registry_resolves_all_collectors(): void
    {
        $registry = app(MetricsRegistry::class);

        $this->assertCount(10, $registry->all());
        foreach ($registry->all() as $collector) {
            $this->assertInstanceOf(MetricsCollector::class, $collector);
        }
    }

    public function test_every_collector_returns_line_list(): void
    {
        foreach (app(MetricsRegistry::class)->all() as $collector) {
            $lines = $collector->collect();
            $this->assertNotEmpty($lines, $collector::class.' emitted nothing');
        }
    }
}
