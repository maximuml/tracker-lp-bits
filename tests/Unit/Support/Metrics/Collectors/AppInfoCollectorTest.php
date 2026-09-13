<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics\Collectors;

use App\Support\Metrics\Collectors\AppInfoCollector;
use App\Support\Metrics\PrometheusFormatter;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AppInfoCollectorTest extends TestCase
{
    public function test_collect_emits_app_info_with_labels(): void
    {
        $lines = (new AppInfoCollector(new PrometheusFormatter))->collect();

        $this->assertContains('# HELP nexus_app_info Application metadata', $lines);
        $this->assertContains('# TYPE nexus_app_info gauge', $lines);

        $info = array_values(array_filter($lines, static fn (string $l) => str_starts_with($l, 'nexus_app_info{')));
        $this->assertCount(1, $info);
        $this->assertStringContainsString('version="', $info[0]);
        $this->assertStringContainsString('env="', $info[0]);
        $this->assertStringEndsWith('} 1', $info[0]);
    }
}
