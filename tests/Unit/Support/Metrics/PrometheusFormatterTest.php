<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Metrics;

use App\Support\Metrics\PrometheusFormatter;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W6-01: exposition format helpers — labels, escaping, bucket boundaries.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class PrometheusFormatterTest extends TestCase
{
    private PrometheusFormatter $fmt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fmt = new PrometheusFormatter;
    }

    public function test_line_without_labels(): void
    {
        $this->assertSame('nexus_db_up 1', $this->fmt->line('nexus_db_up', 1));
    }

    public function test_line_with_labels(): void
    {
        $line = $this->fmt->line('nexus_http_requests_total', 5, ['status' => '200']);

        $this->assertSame('nexus_http_requests_total{status="200"} 5', $line);
    }

    public function test_line_escapes_label_values(): void
    {
        $line = $this->fmt->line('m', 1, ['l' => "a\"b\nc\\d"]);

        $this->assertSame('m{l="a\\"b\\nc\\\\d"} 1', $line);
    }

    public function test_head_returns_help_and_type(): void
    {
        $this->assertSame(
            ['# HELP m help text', '# TYPE m gauge'],
            $this->fmt->head('m', 'help text', 'gauge'),
        );
    }

    public function test_bucket_strips_trailing_zero(): void
    {
        $this->assertSame('1', $this->fmt->bucket(1.0));
        $this->assertSame('0.005', $this->fmt->bucket(0.005));
        $this->assertSame('2.5', $this->fmt->bucket(2.5));
    }
}
