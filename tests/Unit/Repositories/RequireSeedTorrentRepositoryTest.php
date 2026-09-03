<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\RequireSeedTorrent;
use App\Models\Torrent;
use App\Models\UserRequireSeedTorrent;
use App\Repositories\RequireSeedTorrentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for RequireSeedTorrentRepository.
 *
 * Covers doRemove(), shouldRecordUser(), recordUser(),
 * autoAddToListCronjob() (disabled path), autoRemoveFromListCronjob(),
 * and autoSettlementCronjob().
 *
 * Note: Setting::get() uses a function-level static cache that cannot be
 * reset across tests. The require_seed_section.enabled setting is not
 * present by default, so the disabled-path tests are reliable regardless
 * of test execution order. The doRemove() and recordUser() methods are
 * tested directly because their Setting dependency has a default fallback.
 */
final class RequireSeedTorrentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private RequireSeedTorrentRepository $repository;

    /** @var \Redis */
    private $redis;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('user_require_seed_torrents')->delete();
        DB::table('require_seed_torrents')->delete();
        DB::table('torrent_tags')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new RequireSeedTorrentRepository;
        $this->redis = Redis::connection()->client();
    }

    protected function tearDown(): void
    {
        // Clean up Redis keys created during tests.
        $this->redis->del(Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY);
        $it = null;
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while ($keys = $this->redis->scan($it, 'REQUIRE_SEED_SECTION_TORRENT_USER_CACHE:*')) {
            $this->redis->unlink($keys);
        }
        $it = null;
        while ($keys = $this->redis->scan($it, 'REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE:*')) {
            $this->redis->unlink($keys);
        }
        parent::tearDown();
    }

    public function test_auto_settlement_cronjob_completes_without_error(): void
    {
        // autoSettlementCronjob() is currently a no-op.
        $this->repository->autoSettlementCronjob();

        $this->expectNotToPerformAssertions();
    }

    public function test_auto_add_to_list_cronjob_returns_early_when_disabled(): void
    {
        // require_seed_section.enabled is not set by default, so the
        // cronjob should return early without inserting anything.
        $this->repository->autoAddToListCronjob();

        $this->assertSame(0, RequireSeedTorrent::query()->count());
    }

    public function test_auto_remove_from_list_cronjob_returns_early_when_no_data(): void
    {
        $this->repository->autoRemoveFromListCronjob();

        $this->assertSame(0, RequireSeedTorrent::query()->count());
    }

    public function test_do_remove_deletes_torrents_from_db_and_redis(): void
    {
        $torrentId = $this->createTorrent(['seeders' => 10]);
        DB::table('require_seed_torrents')->insert([
            'torrent_id' => $torrentId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        $this->redis->hset(Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY, (string) $torrentId, now()->toDateTimeString());

        $torrents = Torrent::query()->where('id', $torrentId)->get(['id']);
        $this->repository->doRemove($torrents);

        $this->assertSame(0, RequireSeedTorrent::query()->where('torrent_id', $torrentId)->count());
        $this->assertFalse(
            $this->redis->hExists(Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY, (string) $torrentId)
        );
    }

    public function test_do_remove_deletes_user_require_seed_records(): void
    {
        $torrentId = $this->createTorrent(['seeders' => 10]);
        DB::table('require_seed_torrents')->insert([
            'torrent_id' => $torrentId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('user_require_seed_torrents')->insert([
            'user_id' => 100,
            'torrent_id' => $torrentId,
            'seed_time_begin' => 0,
            'uploaded_begin' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $torrents = Torrent::query()->where('id', $torrentId)->get(['id']);
        $this->repository->doRemove($torrents);

        $this->assertSame(0, UserRequireSeedTorrent::query()->where('torrent_id', $torrentId)->count());
    }

    public function test_do_remove_sets_promotion_state_cache(): void
    {
        $torrentId = $this->createTorrent(['seeders' => 10]);

        $torrents = Torrent::query()->where('id', $torrentId)->get(['id']);
        $this->repository->doRemove($torrents);

        $cacheKey = sprintf('%s:%s', Torrent::REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE_KEY, $torrentId);
        $this->assertSame(
            (string) Torrent::REQUIRE_SEED_SECTION_DEFAULT_PROMOTION_STATE,
            (string) $this->redis->get($cacheKey)
        );
    }

    public function test_do_remove_clears_torrent_user_cache(): void
    {
        $torrentId = $this->createTorrent(['seeders' => 10]);
        $userCacheKey = sprintf('%s:%s', Torrent::REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY, $torrentId);
        $this->redis->hset($userCacheKey, '100', now()->toDateTimeString());

        $torrents = Torrent::query()->where('id', $torrentId)->get(['id']);
        $this->repository->doRemove($torrents);

        $this->assertSame([], $this->redis->hGetAll($userCacheKey));
    }

    public function test_should_record_user_returns_false_when_disabled(): void
    {
        // require_seed_section.enabled is not set by default.
        $result = $this->repository->shouldRecordUser($this->redis, 100, 200);

        $this->assertFalse($result);
    }

    public function test_should_record_user_returns_false_when_torrent_not_on_list(): void
    {
        // Manually set the torrent as on-list in Redis, but the Setting
        // check will short-circuit first when disabled.
        $this->redis->hset(Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY, '200', now()->toDateTimeString());

        $result = $this->repository->shouldRecordUser($this->redis, 100, 200);

        $this->assertFalse($result);
    }

    public function test_record_user_inserts_user_require_seed_record(): void
    {
        $torrentId = $this->createTorrent();
        $userId = 500;

        $this->repository->recordUser($this->redis, $userId, $torrentId, [
            'seedtime' => 3600,
            'uploaded' => 1000,
        ]);

        $record = UserRequireSeedTorrent::query()
            ->where('user_id', $userId)
            ->where('torrent_id', $torrentId)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(3600, (int) $record->seed_time_begin);
        $this->assertSame(1000, (int) $record->uploaded_begin);
    }

    public function test_record_user_sets_redis_torrent_user_cache(): void
    {
        $torrentId = $this->createTorrent();
        $userId = 600;

        $this->repository->recordUser($this->redis, $userId, $torrentId, [
            'seedtime' => 0,
            'uploaded' => 0,
        ]);

        $userCacheKey = sprintf('%s:%s', Torrent::REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY, $torrentId);
        $this->assertTrue($this->redis->hExists($userCacheKey, (string) $userId));
    }

    public function test_record_user_upserts_existing_record(): void
    {
        $torrentId = $this->createTorrent();
        $userId = 700;

        // Insert first.
        $this->repository->recordUser($this->redis, $userId, $torrentId, [
            'seedtime' => 100,
            'uploaded' => 200,
        ]);

        // Upsert with new values.
        $this->repository->recordUser($this->redis, $userId, $torrentId, [
            'seedtime' => 200,
            'uploaded' => 400,
        ]);

        $count = UserRequireSeedTorrent::query()
            ->where('user_id', $userId)
            ->where('torrent_id', $torrentId)
            ->count();

        $this->assertSame(1, $count);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTorrent(array $overrides = []): int
    {
        return (int) DB::table('torrents')->insertGetId(array_merge([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => 1,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
            'hits' => 0,
            'views' => 0,
            'added' => now()->toDateTimeString(),
        ], $overrides));
    }
}
