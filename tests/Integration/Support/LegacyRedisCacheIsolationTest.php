<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Cache\LegacyRedisCache;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * UX-03a regression: the legacy phpredis cache must honor the test
 * isolation (REDIS_DB=15 via phpunit <server> → database.redis.default).
 * Previously `App\Support\Env::get()` read the .env file before
 * $_SERVER/$_ENV, so the override never reached the cache config and every
 * legacy cache key was written into the dev keyspace (DB 0) — real
 * symptom: the dev site's `category_content` held a single test fixture
 * row, emptying every category name in torrent lists (axe link-name).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class LegacyRedisCacheIsolationTest extends TestCase
{
    public function test_redis_config_honors_server_env_overrides(): void
    {
        // phpunit.xml sets <server name="REDIS_DB" value="15"/> — the
        // database.redis.default connection must see it even though .env
        // pins REDIS_DB=0.
        $this->assertSame(15, (int) config('database.redis.default.database'));
    }

    public function test_cache_value_lands_in_test_db_not_dev_db0(): void
    {
        $cache = app(LegacyRedisCache::class);
        if (! $cache->getIsEnabled()) {
            $this->markTestSkipped('Redis unavailable');
        }
        $testDb = (int) config('database.redis.default.database');
        $this->assertNotSame(0, $testDb, 'tests must not run against the dev Redis DB');

        $probe = 'ux03_isolation_probe';
        $cache->cache_value($probe, 'probe', 60);
        try {
            $this->assertSame('probe', $cache->get_value($probe));

            // Raw client on the dev keyspace (DB 0) must NOT see the test key.
            $devRedis = new \Redis;
            $devRedis->connect((string) config('database.redis.default.host'), (int) config('database.redis.default.port'));
            $password = config('database.redis.default.password');
            if ($password !== null && $password !== '') {
                $devRedis->auth($password);
            }
            $devRedis->select(0);
            $this->assertFalse($devRedis->get($probe), 'test write leaked into dev Redis DB 0');
            $devRedis->close();
        } finally {
            $cache->delete_value($probe);
        }
    }
}
