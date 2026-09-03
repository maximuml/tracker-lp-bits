<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\MetricsController;
use Tests\TestCase;

final class MetricsControllerTest extends TestCase
{
    public function test_index_returns_prometheus_format(): void
    {
        $controller = app(MetricsController::class);

        $response = $controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'text/plain; version=0.0.4; charset=utf-8',
            $response->headers->get('Content-Type')
        );
    }

    public function test_index_output_contains_metric_names(): void
    {
        $controller = app(MetricsController::class);

        $response = $controller->index();
        $body = (string) $response->getContent();

        $this->assertNotEmpty($body);
    }

    public function test_index_output_is_non_empty_text(): void
    {
        $controller = app(MetricsController::class);

        $response = $controller->index();
        $body = (string) $response->getContent();

        $this->assertNotSame('', trim($body));
    }
}
