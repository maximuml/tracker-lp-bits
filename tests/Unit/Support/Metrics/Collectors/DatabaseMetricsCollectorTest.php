<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\DatabaseMetricsCollector;
use App\Support\Metrics\PrometheusFormatter;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class DatabaseMetricsCollectorTest extends TestCase
{
    public function test_collect_emits_db_up_and_query_count(): void
    {
        $lines = (new DatabaseMetricsCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# HELP nexus_db_up Database connectivity (1=up, 0=down)', $lines);
        $this->assertContains('# TYPE nexus_db_up gauge', $lines);
        $this->assertContains('nexus_db_up 1', $lines);
        $this->assertContains('# TYPE nexus_db_query_count gauge', $lines);

        $count = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_db_query_count ')));
        $this->assertCount(1, $count);
    }
}
