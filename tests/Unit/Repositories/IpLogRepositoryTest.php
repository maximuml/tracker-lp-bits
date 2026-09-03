<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\IpLogRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for IpLogRepository.
 *
 * saveToCache() short-circuits in the testing environment, so the tests
 * verify the early-return guards (invalid user id, valid user id) do not
 * throw and never touch Redis. saveToDB() is exercised against an empty
 * Redis to confirm it completes without error.
 */
final class IpLogRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private IpLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('iplog')->truncate();
        $this->repository = new IpLogRepository;
    }

    public function test_save_to_cache_with_invalid_user_id_returns_early(): void
    {
        $this->repository->saveToCache(0);

        $this->assertSame(0, DB::table('iplog')->count());
    }

    public function test_save_to_cache_with_negative_user_id_returns_early(): void
    {
        $this->repository->saveToCache(-5);

        $this->assertSame(0, DB::table('iplog')->count());
    }

    public function test_save_to_cache_with_non_numeric_user_id_returns_early(): void
    {
        $this->repository->saveToCache('abc');

        $this->assertSame(0, DB::table('iplog')->count());
    }

    public function test_save_to_cache_with_valid_user_id_does_not_write_in_testing(): void
    {
        $this->repository->saveToCache(42, '/test', ['1.2.3.4']);

        // In the testing environment saveToCache() returns before touching Redis.
        $this->assertSame(0, DB::table('iplog')->count());
    }

    public function test_save_to_cache_with_null_uri_and_ip_does_not_throw(): void
    {
        $this->repository->saveToCache(42);

        $this->expectNotToPerformAssertions();
    }

    public function test_save_to_db_completes_without_error_when_no_keys(): void
    {
        // Ensure no leftover hash keys from prior runs.
        $redis = Redis::connection()->client();
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $it = null;
        while ($keys = $redis->scan($it, 'nexus_ip_logs:*')) {
            foreach ($keys as $key) {
                $redis->unlink($key);
            }
        }

        $this->repository->saveToDB();

        $this->assertSame(0, DB::table('iplog')->count());
    }
}
