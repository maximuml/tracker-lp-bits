<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\AssertsQueryCount;
use Tests\TestCase;

/**
 * Wave 5 Step 23: Query-count budgets for key pages.
 *
 * Enforces maximum DB query counts per request to catch N+1 problems
 * early. Budgets are set generously above current counts to avoid
 * flaky tests, but low enough to catch regressions.
 *
 * @group query-budget
 */
final class QueryBudgetTest extends TestCase
{
    use AssertsQueryCount;

    /**
     * Health/live endpoint should use minimal DB queries.
     * (Laravel bootstrap may run a few session/auth queries even on
     * stateless endpoints — budget allows for framework overhead.)
     */
    public function test_health_live_query_budget(): void
    {
        $this->assertQueryCountBelow(10, function (): void {
            $this->getJson('/health/live');
        });
    }

    /**
     * Health/ready endpoint should use at most 1 DB query (DB ping).
     */
    public function test_health_ready_query_budget(): void
    {
        $this->assertQueryCountBelow(5, function (): void {
            $this->getJson('/health/ready');
        });
    }

    /**
     * Metrics endpoint should use at most 2 DB queries (DB ping).
     */
    public function test_metrics_query_budget(): void
    {
        $this->assertQueryCountBelow(5, function (): void {
            $this->get('/metrics');
        });
    }
}
