<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * One domain of the /metrics exposition (W6-01).
 *
 * Implementations must not throw: a failing collector degrades to its
 * HELP/TYPE headers (or fewer lines) instead of taking down the endpoint.
 * The controller additionally isolates each collector behind try/catch.
 */
interface MetricsCollector
{
    /**
     * @return list<string> Prometheus text exposition lines
     */
    public function collect(): array;
}
