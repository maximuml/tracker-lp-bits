<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Wave 5 Step 23: Performance budgets — infrastructure verification.
 *
 * Verifies that:
 * - k6 baseline.js has performance budgets for key pages
 * - k6 announce.js exists for separate announce load testing
 * - Query-count budget trait exists for feature tests
 * - Query log is disabled in production (AppServiceProvider)
 * - FPM pm.max_children is configured in Dockerfile.prod
 * - Resource limits are set in docker-compose.prod.yml
 */
final class PerformanceBudgetTest extends TestCase
{
    /**
     * k6 baseline.js has budgets for key pages.
     */
    public function test_k6_baseline_has_budgets(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString('BUDGETS', $baseline, 'baseline.js must define BUDGETS');
        $this->assertStringContainsString('page_index_duration', $baseline, 'baseline.js must have index budget');
        $this->assertStringContainsString('page_health_live_duration', $baseline, 'baseline.js must have health/live budget');
        $this->assertStringContainsString('page_health_ready_duration', $baseline, 'baseline.js must have health/ready budget');
    }

    /**
     * k6 baseline.js includes extended page coverage (torrents, forums, faq, rules, metrics).
     */
    public function test_k6_baseline_has_extended_pages(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString('page_torrents_duration', $baseline, 'baseline.js must have torrents budget');
        $this->assertStringContainsString('page_forums_duration', $baseline, 'baseline.js must have forums budget');
        $this->assertStringContainsString('page_faq_duration', $baseline, 'baseline.js must have faq budget');
        $this->assertStringContainsString('page_rules_duration', $baseline, 'baseline.js must have rules budget');
        $this->assertStringContainsString('page_metrics_duration', $baseline, 'baseline.js must have metrics budget');
    }

    /**
     * k6 announce.js exists for separate announce load testing.
     */
    public function test_k6_announce_load_test_exists(): void
    {
        $this->assertFileExists(base_path('tests/Performance/announce.js'));
        $announce = file_get_contents(base_path('tests/Performance/announce.js'));
        $this->assertStringContainsString('announce.php', $announce, 'announce.js must test announce.php');
        $this->assertStringContainsString('announce_duration', $announce, 'announce.js must track announce duration');
    }

    /**
     * Query-count budget trait exists.
     */
    public function test_query_count_budget_trait_exists(): void
    {
        $this->assertFileExists(base_path('tests/Concerns/AssertsQueryCount.php'));
        $trait = file_get_contents(base_path('tests/Concerns/AssertsQueryCount.php'));
        $this->assertStringContainsString('assertQueryCountBelow', $trait, 'Trait must have assertQueryCountBelow method');
        $this->assertStringContainsString('enableQueryLog', $trait, 'Trait must enable query log');
    }

    /**
     * QueryBudgetTest feature test exists.
     */
    public function test_query_budget_feature_test_exists(): void
    {
        $this->assertFileExists(base_path('tests/Feature/QueryBudgetTest.php'));
        $test = file_get_contents(base_path('tests/Feature/QueryBudgetTest.php'));
        $this->assertStringContainsString('AssertsQueryCount', $test, 'QueryBudgetTest must use AssertsQueryCount trait');
    }

    /**
     * Query log is disabled in production (AppServiceProvider).
     */
    public function test_query_log_disabled_in_production(): void
    {
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('enableQueryLog', $provider, 'AppServiceProvider must reference enableQueryLog');
        $this->assertStringContainsString('isProduction', $provider, 'AppServiceProvider must check isProduction');
        $this->assertStringContainsString('! app()->isProduction()', $provider, 'Query log must be disabled in production');
    }

    /**
     * FPM pm.max_children is configured in Dockerfile.prod.
     */
    public function test_dockerfile_prod_has_fpm_tuning(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/php/Dockerfile.prod'));
        $this->assertStringContainsString('pm.max_children', $dockerfile, 'Dockerfile.prod must set pm.max_children');
        $this->assertStringContainsString('pm.max_requests', $dockerfile, 'Dockerfile.prod must set pm.max_requests');
        $this->assertStringContainsString('pm.start_servers', $dockerfile, 'Dockerfile.prod must set pm.start_servers');
    }

    /**
     * Resource limits are set in docker-compose.prod.yml.
     */
    public function test_prod_compose_has_resource_limits(): void
    {
        $compose = file_get_contents(base_path('docker-compose.prod.yml'));
        $this->assertStringContainsString('deploy:', $compose, 'prod compose must have deploy section');
        $this->assertStringContainsString('limits:', $compose, 'prod compose must have resource limits');
        $this->assertStringContainsString('memory:', $compose, 'prod compose must have memory limits');
        $this->assertStringContainsString('cpus:', $compose, 'prod compose must have CPU limits');
    }

    /**
     * perf-budget CI workflow exists and runs k6.
     */
    public function test_perf_budget_ci_workflow_exists(): void
    {
        $this->assertFileExists(base_path('.github/workflows/perf-budget.yml'));
        $workflow = file_get_contents(base_path('.github/workflows/perf-budget.yml'));
        $this->assertStringContainsString('k6', $workflow, 'perf-budget workflow must use k6');
        $this->assertStringContainsString('baseline.js', $workflow, 'perf-budget workflow must run baseline.js');
    }
}
