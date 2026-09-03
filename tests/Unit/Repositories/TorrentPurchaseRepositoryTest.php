<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Snatch;
use App\Models\TorrentBuyLog;
use App\Repositories\TorrentPurchaseRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for TorrentPurchaseRepository.
 *
 * Covers loadBoughtUser(), addBuySuccessCache(), hasBuySuccessCache(),
 * hasBuySuccess(), getBuyStatus(), addBuyFailCache(), getBuyFailCache(),
 * getBoughtUserCacheKey(), and getBuyFailCacheKey().
 *
 * Uses Redis directly and cleans up keys in tearDown to avoid cross-test
 * contamination.
 */
final class TorrentPurchaseRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentPurchaseRepository $repository;

    /** @var \Redis */
    private $redis;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — torrent_buy_logs
        // and snatched have FK constraints to torrents/users but tests insert
        // with arbitrary IDs.  Use DELETE (DML) instead of TRUNCATE (DDL) to
        // avoid an implicit commit that would break DatabaseTransactions
        // rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('snatched')->delete();
        DB::table('torrent_buy_logs')->delete();
        $this->repository = new TorrentPurchaseRepository;
        $this->redis = Redis::connection()->client();
    }

    protected function tearDown(): void
    {
        // Clean up any Redis keys created during tests.
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $it = null;
        while ($keys = $this->redis->scan($it, 'torrent_purchasers:*')) {
            $this->redis->unlink($keys);
        }
        $it = null;
        while ($keys = $this->redis->scan($it, 'torrent_purchase_fails:*')) {
            $this->redis->unlink($keys);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_bought_user_cache_key_formats_correctly(): void
    {
        $this->assertSame(
            'torrent_purchasers:100:200',
            $this->repository->getBoughtUserCacheKey(100, 200)
        );
    }

    public function test_get_buy_fail_cache_key_formats_correctly(): void
    {
        $this->assertSame(
            'torrent_purchase_fails:100:200',
            $this->repository->getBuyFailCacheKey(100, 200)
        );
    }

    public function test_has_buy_success_cache_returns_false_when_no_key(): void
    {
        $this->assertFalse($this->repository->hasBuySuccessCache(999, 999));
    }

    public function test_has_buy_success_cache_returns_true_after_load(): void
    {
        $torrentId = 1001;
        $uid = 2001;

        TorrentBuyLog::query()->insert([
            'uid' => $uid,
            'torrent_id' => $torrentId,
            'price' => 10,
            'channel' => 'test',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->repository->loadBoughtUser($torrentId);

        $this->assertTrue($this->repository->hasBuySuccessCache($uid, $torrentId));
    }

    public function test_load_bought_user_returns_total_count(): void
    {
        $torrentId = 1002;

        TorrentBuyLog::query()->insert([
            ['uid' => 1, 'torrent_id' => $torrentId, 'price' => 10, 'channel' => 'test', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['uid' => 2, 'torrent_id' => $torrentId, 'price' => 10, 'channel' => 'test', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['uid' => 3, 'torrent_id' => $torrentId, 'price' => 10, 'channel' => 'test', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ]);

        $total = $this->repository->loadBoughtUser($torrentId);

        $this->assertSame(3, $total);
    }

    public function test_load_bought_user_returns_zero_when_no_logs(): void
    {
        $this->assertSame(0, $this->repository->loadBoughtUser(9999));
    }

    public function test_add_buy_success_cache_sets_redis_key(): void
    {
        $uid = 3001;
        $torrentId = 3002;
        $buyLogId = 3003;

        // Create a snatch record so addBuySuccessCache updates it.
        Snatch::query()->insert([
            'torrentid' => $torrentId, 'userid' => $uid, 'ip' => '1.1.1.1', 'port' => 5000,
            'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'seedtime' => 0, 'leechtime' => 0,
            'startdat' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString(),
            'finished' => 0,
        ]);

        $this->repository->addBuySuccessCache($uid, $torrentId, $buyLogId);

        $this->assertTrue($this->repository->hasBuySuccessCache($uid, $torrentId));

        // The snatch record should have buy_log_id updated.
        $snatch = Snatch::query()->where('torrentid', $torrentId)->where('userid', $uid)->first();
        $this->assertNotNull($snatch);
        $this->assertSame($buyLogId, (int) $snatch->buy_log_id);
    }

    public function test_add_buy_success_cache_does_not_throw_when_snatch_missing(): void
    {
        // No snatch record exists — should log error but not throw.
        $this->repository->addBuySuccessCache(4001, 4002, 4003);

        $this->assertTrue($this->repository->hasBuySuccessCache(4001, 4002));
    }

    public function test_has_buy_success_returns_false_when_no_cache_and_no_log(): void
    {
        $this->assertFalse($this->repository->hasBuySuccess(5001, 5002));
    }

    public function test_has_buy_success_returns_true_when_buy_log_exists(): void
    {
        $uid = 5003;
        $torrentId = 5004;

        TorrentBuyLog::query()->insert([
            'uid' => $uid, 'torrent_id' => $torrentId, 'price' => 10, 'channel' => 'test',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($this->repository->hasBuySuccess($uid, $torrentId));
        // After hasBuySuccess, the cache should be populated.
        $this->assertTrue($this->repository->hasBuySuccessCache($uid, $torrentId));
    }

    public function test_get_buy_status_returns_success_when_bought(): void
    {
        $uid = 6001;
        $torrentId = 6002;

        TorrentBuyLog::query()->insert([
            'uid' => $uid, 'torrent_id' => $torrentId, 'price' => 10, 'channel' => 'test',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame(
            TorrentPurchaseRepository::BUY_STATUS_SUCCESS,
            $this->repository->getBuyStatus($uid, $torrentId)
        );
    }

    public function test_get_buy_status_returns_fail_count_when_fail_cache_exists(): void
    {
        $uid = 7001;
        $torrentId = 7002;

        $this->repository->addBuyFailCache($uid, $torrentId);
        $this->repository->addBuyFailCache($uid, $torrentId);

        $this->assertSame(2, $this->repository->getBuyStatus($uid, $torrentId));
    }

    public function test_get_buy_status_returns_unknown_when_no_success_and_no_fail(): void
    {
        $this->assertSame(
            TorrentPurchaseRepository::BUY_STATUS_UNKNOWN,
            $this->repository->getBuyStatus(8001, 8002)
        );
    }

    public function test_add_buy_fail_cache_increments_count(): void
    {
        $uid = 9001;
        $torrentId = 9002;

        $this->repository->addBuyFailCache($uid, $torrentId);
        $this->assertSame(1, $this->repository->getBuyFailCache($uid, $torrentId));

        $this->repository->addBuyFailCache($uid, $torrentId);
        $this->assertSame(2, $this->repository->getBuyFailCache($uid, $torrentId));
    }

    public function test_get_buy_fail_cache_returns_zero_when_no_key(): void
    {
        $this->assertSame(0, $this->repository->getBuyFailCache(9991, 9992));
    }
}
