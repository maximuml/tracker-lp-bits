<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Query-count budget trait for feature tests.
 *
 * Catches N+1 query problems by asserting that a request does not
 * exceed a maximum number of database queries.
 *
 * Usage:
 *   use AssertsQueryCount;
 *
 *   public function test_index_page_query_budget(): void
 *   {
 *       $this->assertQueryCountBelow(50, function () {
 *           $this->get('/index.php');
 *       });
 *   }
 *
 * The query log is enabled automatically in non-production environments
 * by AppServiceProvider. In tests, we ensure it's enabled explicitly.
 */
trait AssertsQueryCount
{
    /**
     * Assert that the number of DB queries executed within the callback
     * is below the given budget.
     *
     * @param  int  $budget  Maximum allowed queries
     * @param  callable  $callback  Code to execute while counting queries
     */
    protected function assertQueryCountBelow(int $budget, callable $callback): void
    {
        $connection = DB::connection(config('database.default'));
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $callback();

        $count = count($connection->getQueryLog());
        $connection->flushQueryLog();

        $this->assertLessThan(
            $budget,
            $count,
            "Query count budget exceeded: {$count} queries (budget: {$budget}). Possible N+1 query problem."
        );
    }
}
