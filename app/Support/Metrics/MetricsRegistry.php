<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Ordered list of metrics collectors behind /metrics (W6-01).
 */
final class MetricsRegistry
{
    /**
     * @param  list<MetricsCollector>  $collectors
     */
    public function __construct(private readonly array $collectors) {}

    /**
     * @return list<MetricsCollector>
     */
    public function all(): array
    {
        return $this->collectors;
    }
}
