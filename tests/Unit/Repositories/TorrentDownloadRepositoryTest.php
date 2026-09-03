<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\TorrentSecret;
use App\Models\User;
use App\Repositories\TorrentDownloadRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for TorrentDownloadRepository.
 *
 * Covers getDownloadUrl(), encryptDownHash(), decryptDownHash(),
 * getTrackerReportAuthKey(), checkTrackerReportAuthKey(),
 * resetTrackerReportAuthKeySecret(), pieces-hash cache methods,
 * and touch/reset cache stamp.
 */
final class TorrentDownloadRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentDownloadRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_secrets')->delete();
        Cache::flush();
        $this->repository = new TorrentDownloadRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $redis = Redis::connection()->client();
        try {
            $redis->del(TorrentDownloadRepository::PIECES_HASH_CACHE_KEY);
        } catch (\Exception) {
            // ignore
        }
        parent::tearDown();
    }

    public function test_get_download_url_contains_downhash_and_user_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $url = $this->repository->getDownloadUrl(42, $user);

        $this->assertStringContainsString('download.php?downhash=', $url);
        $this->assertStringContainsString($user->id.'.', $url);
    }

    public function test_get_download_url_accepts_array_user(): void
    {
        $userArray = ['id' => 99, 'passkey' => bin2hex(random_bytes(8))];

        $url = $this->repository->getDownloadUrl(7, $userArray);

        $this->assertStringContainsString('downhash=99.', $url);
    }

    public function test_encrypt_and_decrypt_down_hash_roundtrip_with_model(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $downHash = $this->repository->encryptDownHash(123, $user);
        $this->assertNotEmpty($downHash);

        $decoded = $this->repository->decryptDownHash($downHash, $user);

        $this->assertSame([123], $decoded);
    }

    public function test_encrypt_and_decrypt_down_hash_roundtrip_with_array(): void
    {
        $userArray = ['id' => 55, 'passkey' => bin2hex(random_bytes(8))];

        $downHash = $this->repository->encryptDownHash(77, $userArray);
        $decoded = $this->repository->decryptDownHash($downHash, $userArray);

        $this->assertSame([77], $decoded);
    }

    public function test_decrypt_down_hash_returns_empty_for_invalid_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $decoded = $this->repository->decryptDownHash('invalid.jwt.token', $user);

        $this->assertSame([], $decoded);
    }

    public function test_decrypt_down_hash_returns_empty_for_wrong_user(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $downHash = $this->repository->encryptDownHash(100, $user1);
        $decoded = $this->repository->decryptDownHash($downHash, $user2);

        $this->assertSame([], $decoded);
    }

    public function test_encrypt_down_hash_throws_for_user_without_passkey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->encryptDownHash(1, ['id' => 1, 'passkey' => '']);
    }

    public function test_get_tracker_report_auth_key_returns_formatted_string(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $authKey = $this->repository->getTrackerReportAuthKey(10, $user->id, true);

        $parts = explode('|', $authKey);
        $this->assertCount(3, $parts);
        $this->assertSame('10', $parts[0]);
        $this->assertSame((string) $user->id, $parts[1]);
    }

    public function test_check_tracker_report_auth_key_decodes_successfully(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Pre-create the secret so the cache doesn't store a stale false
        $this->repository->resetTrackerReportAuthKeySecret($user->id, 0);
        Cache::flush();

        $authKey = $this->repository->getTrackerReportAuthKey(20, $user->id, true);
        $decoded = $this->repository->checkTrackerReportAuthKey($authKey);

        $this->assertNotEmpty($decoded);
    }

    public function test_check_tracker_report_auth_key_throws_for_invalid_format(): void
    {
        $this->expectException(NexusException::class);

        $this->repository->checkTrackerReportAuthKey('invalid');
    }

    public function test_get_tracker_report_auth_key_throws_when_no_secret_and_no_init(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(NexusException::class);

        $this->repository->getTrackerReportAuthKey(999, $user->id, false);
    }

    public function test_reset_tracker_report_auth_key_secret_with_zero_torrent_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $secret = $this->repository->resetTrackerReportAuthKeySecret($user->id, 0);

        $this->assertNotEmpty($secret);
        $count = TorrentSecret::query()->where('uid', $user->id)->where('torrent_id', 0)->count();
        $this->assertSame(1, $count);
    }

    public function test_reset_tracker_report_auth_key_secret_with_specific_torrent(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $secret = $this->repository->resetTrackerReportAuthKeySecret($user->id, 50);

        $this->assertNotEmpty($secret);
        $exists = TorrentSecret::query()->where('uid', $user->id)->where('torrent_id', 50)->exists();
        $this->assertTrue($exists);
    }

    public function test_reset_tracker_report_auth_key_secret_replaces_existing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->resetTrackerReportAuthKeySecret($user->id, 0);
        $newSecret = $this->repository->resetTrackerReportAuthKeySecret($user->id, 0);

        $count = TorrentSecret::query()->where('uid', $user->id)->where('torrent_id', 0)->count();
        $this->assertSame(1, $count);
        $this->assertNotEmpty($newSecret);
    }

    public function test_add_and_get_pieces_hash_cache(): void
    {
        $piecesHash = 'abc123def456';
        $torrentId = 42;

        $this->repository->addPiecesHashCache($torrentId, $piecesHash);

        $result = $this->repository->getPiecesHashCache($piecesHash);

        $this->assertArrayHasKey($piecesHash, $result);
        $this->assertSame($torrentId, $result[$piecesHash]);
    }

    public function test_get_pieces_hash_cache_returns_empty_for_missing_hash(): void
    {
        $result = $this->repository->getPiecesHashCache('nonexistent_hash');

        $this->assertSame([], $result);
    }

    public function test_get_pieces_hash_cache_accepts_array_of_hashes(): void
    {
        $this->repository->addPiecesHashCache(1, 'hash_one');
        $this->repository->addPiecesHashCache(2, 'hash_two');

        $result = $this->repository->getPiecesHashCache(['hash_one', 'hash_two', 'missing']);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result['hash_one']);
        $this->assertSame(2, $result['hash_two']);
    }

    public function test_get_pieces_hash_cache_throws_for_too_many_hashes(): void
    {
        $hashes = array_fill(0, 101, 'h');

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->getPiecesHashCache($hashes);
    }

    public function test_del_pieces_hash_cache_removes_entry(): void
    {
        $this->repository->addPiecesHashCache(99, 'to_delete');
        $this->repository->delPiecesHashCache('to_delete');

        $result = $this->repository->getPiecesHashCache('to_delete');

        $this->assertSame([], $result);
    }

    public function test_touch_cache_stamp_sets_timestamp(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $before = time();
        $this->repository->touchCacheStamp($torrent->id);
        $after = time();

        $stamp = (int) DB::table('torrents')->where('id', $torrent->id)->value('cache_stamp');
        $this->assertGreaterThanOrEqual($before, $stamp);
        $this->assertLessThanOrEqual($after, $stamp);
    }

    public function test_reset_cache_stamp_sets_zero(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        DB::table('torrents')->where('id', $torrent->id)->update(['cache_stamp' => time()]);

        $this->repository->resetCacheStamp($torrent->id);

        $stamp = (int) DB::table('torrents')->where('id', $torrent->id)->value('cache_stamp');
        $this->assertSame(0, $stamp);
    }
}
