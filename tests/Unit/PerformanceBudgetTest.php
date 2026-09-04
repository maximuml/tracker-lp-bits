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
     * T-17: k6 baseline.js uses authenticated login (CSRF + session cookie).
     */
    public function test_k6_baseline_has_authenticated_login(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString('csrf', $baseline, 'baseline.js must extract CSRF token (T-17)');
        $this->assertStringContainsString('sessionCookies', $baseline, 'baseline.js must use session cookies (T-17)');
        $this->assertStringContainsString('USERNAME', $baseline, 'baseline.js must accept USERNAME env (T-17)');
        $this->assertStringContainsString('PASSWORD', $baseline, 'baseline.js must accept PASSWORD env (T-17)');
    }

    /**
     * T-17: k6 baseline.js has strict failure threshold (< 0.01, not 0.50).
     */
    public function test_k6_baseline_has_strict_failure_threshold(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString("rate<0.01", $baseline, 'baseline.js must have http_req_failed < 0.01 (T-17)');
        $this->assertStringNotContainsString("rate<0.50", $baseline, 'baseline.js must not have loose 0.50 threshold (T-17)');
    }

    /**
     * T-17: PerformanceTestDatasetSeeder exists for deterministic test data.
     */
    public function test_performance_dataset_seeder_exists(): void
    {
        $this->assertFileExists(base_path('database/seeders/PerformanceTestDatasetSeeder.php'));
        $seeder = file_get_contents(base_path('database/seeders/PerformanceTestDatasetSeeder.php'));
        $this->assertStringContainsString('perf_user_', $seeder, 'Seeder must create perf_user_* accounts');
        $this->assertStringContainsString('perf-torrent-', $seeder, 'Seeder must create perf-torrent-* torrents');
        $this->assertStringContainsString('PerfTest2026!', $seeder, 'Seeder must use known password');
    }

    /**
     * k6 baseline.js includes extended page coverage (T-17: authenticated scenarios).
     */
    public function test_k6_baseline_has_extended_pages(): void
    {
        $baseline = file_get_contents(base_path('tests/Performance/baseline.js'));
        $this->assertStringContainsString('page_browse_duration', $baseline, 'baseline.js must have browse budget');
        $this->assertStringContainsString('page_search_duration', $baseline, 'baseline.js must have search budget');
        $this->assertStringContainsString('page_details_duration', $baseline, 'baseline.js must have details budget');
        $this->assertStringContainsString('page_messages_duration', $baseline, 'baseline.js must have messages budget');
        $this->assertStringContainsString('page_usercp_duration', $baseline, 'baseline.js must have usercp budget');
        $this->assertStringContainsString('page_upload_duration', $baseline, 'baseline.js must have upload budget');
    }

    /**
     * k6 announce.js exists for separate announce load testing (T-17: passkey + scrape).
     */
    public function test_k6_announce_load_test_exists(): void
    {
        $this->assertFileExists(base_path('tests/Performance/announce.js'));
        $announce = file_get_contents(base_path('tests/Performance/announce.js'));
        $this->assertStringContainsString('announce.php', $announce, 'announce.js must test announce.php');
        $this->assertStringContainsString('announce_duration', $announce, 'announce.js must track announce duration');
        $this->assertStringContainsString('scrape.php', $announce, 'announce.js must test scrape.php (T-17)');
        $this->assertStringContainsString('passkey', $announce, 'announce.js must use passkey auth (T-17)');
        $this->assertStringContainsString('scrape_duration', $announce, 'announce.js must track scrape duration (T-17)');
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
