<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Cache\LegacyRedisCache;
use App\Support\Config;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * UX-03a regression: the legacy phpredis cache must honor the test
 * isolation (`nexus.redis.database` = REDIS_DB=15 via phpunit <server>).
 * Previously `App\Support\Env::get()` read the .env file before
 * $_SERVER/$_ENV, so the override never reached `nexus.redis` and every
 * legacy cache key was written into the dev keyspace (DB 0) — real
 * symptom: the dev site's `category_content` held a single test fixture
 * row, emptying every category name in torrent lists (axe link-name).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class LegacyRedisCacheIsolationTest extends TestCase
{
    public function test_redis_config_honors_server_env_overrides(): void
    {
        // phpunit.xml sets <server name="REDIS_DB" value="15"/> — nexus.*
        // config must see it even though .env pins REDIS_DB=0.
        $this->assertSame('15', (string) Config::get('nexus.redis.database'));
    }

    public function test_cache_value_lands_in_test_db_not_dev_db0(): void
    {
        $cache = app(LegacyRedisCache::class);
        if (! $cache->getIsEnabled()) {
            $this->markTestSkipped('Redis unavailable');
        }
        $testDb = (int) Config::get('nexus.redis.database');
        $this->assertNotSame(0, $testDb, 'tests must not run against the dev Redis DB');

        $key = 'ux03_isolation_probe';
        $cache->cache_value($key, 'probe', 60);
        try {
            $this->assertSame('probe', $cache->get_value($key));

            // Raw client on the dev keyspace (DB 0) must NOT see the test key.
            $devRedis = new \Redis;
            $devRedis->connect((string) Config::get('nexus.redis.host'), (int) Config::get('nexus.redis.port'));
            $password = Config::get('nexus.redis.password');
            if ($password !== null && $password !== '') {
                $devRedis->auth($password);
            }
            $devRedis->select(0);
            $this->assertFalse($devRedis->get($key), 'test write leaked into dev Redis DB 0');
            $devRedis->close();
        } finally {
            $cache->delete_value($key);
        }
    }
}
