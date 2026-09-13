<?php

declare(strict_types=1);

namespace App\Support\Metrics\Collectors;

use App\Support\Metrics\MetricsCollector;
use App\Support\Metrics\PrometheusFormatter;

/**
 * Application info metric.
 */
final class AppInfoCollector implements MetricsCollector
{
    public function __construct(private readonly PrometheusFormatter $fmt) {}

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $lines = $this->fmt->head('nexus_app_info', 'Application metadata', 'gauge');
        $lines[] = $this->fmt->line('nexus_app_info', 1, [
            'version' => (string) config('app.version', 'unknown'),
            'env' => (string) config('app.env', 'unknown'),
        ]);

        return $lines;
    }
}
