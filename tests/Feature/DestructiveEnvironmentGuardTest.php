<?php

namespace Tests\Feature;

use App\Support\DestructiveEnvironmentGuard;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies that {@see DestructiveEnvironmentGuard} refuses to run tests
 * against a database or Redis namespace that is not explicitly marked as a
 * test environment.
 *
 * The guard is the safety net for T-05 (test database isolation): even if a
 * developer or CI accidentally sets DB_DATABASE to the dev/production
 * database, the test suite fails before any query runs.
 */
final class DestructiveEnvironmentGuardTest extends TestCase
{
    /**
     * Non-testing environments are not gated — the guard is a no-op.
     */
    public function test_guard_is_noop_in_non_testing_environment(): void
    {
        // No exception should be thrown for any DB name when APP_ENV is not
        // "testing".
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'production',
            'db_database' => 'nexusphp',
            'redis_prefix' => '',
        ]);
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'local',
            'db_database' => 'nexusphp',
            'redis_prefix' => '',
        ]);

        $this->assertTrue(true);
    }

    /**
     * A database name with the "testing" marker is accepted.
     */
    public function test_guard_accepts_testing_marker_in_database_name(): void
    {
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp_unit_testing',
            'redis_prefix' => 'nexusphp_test_',
        ]);
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp_feature_testing',
            'redis_prefix' => 'nexusphp_test_',
        ]);
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp_e2e_testing',
            'redis_prefix' => 'nexusphp_test_',
        ]);

        $this->assertTrue(true);
    }

    /**
     * A database name with the "test" marker (not "testing") is also accepted.
     */
    public function test_guard_accepts_test_marker_in_database_name(): void
    {
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'my_test_db',
            'redis_prefix' => 'test_',
        ]);

        $this->assertTrue(true);
    }

    /**
     * The "e2e" marker alone is sufficient (case-insensitive).
     */
    public function test_guard_accepts_e2e_marker_case_insensitive(): void
    {
        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'NexusPHP_E2E',
            'redis_prefix' => 'test_',
        ]);

        $this->assertTrue(true);
    }

    /**
     * A production-style database name (no test marker) is rejected.
     */
    public function test_guard_rejects_production_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against database "nexusphp"');

        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp',
            'redis_prefix' => 'nexusphp_test_',
        ]);
    }

    /**
     * An empty database name is rejected.
     */
    public function test_guard_rejects_empty_database_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against database ""');

        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => '',
            'redis_prefix' => 'nexusphp_test_',
        ]);
    }

    /**
     * A Redis prefix without the "test" marker is rejected.
     */
    public function test_guard_rejects_redis_prefix_without_test_marker(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests with Redis prefix "nexusphp_"');

        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp_testing',
            'redis_prefix' => 'nexusphp_',
        ]);
    }

    /**
     * An empty Redis prefix is rejected.
     */
    public function test_guard_rejects_empty_redis_prefix(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests with Redis prefix ""');

        DestructiveEnvironmentGuard::assertTestingEnvironment([
            'app_env' => 'testing',
            'db_database' => 'nexusphp_testing',
            'redis_prefix' => '',
        ]);
    }
}
