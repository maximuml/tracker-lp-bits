<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\TorrentAjaxRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentAjaxRepository.
 *
 * Covers fileList(), searchSuggest(), snatchList(), and userTorrentList().
 *
 * The peerList() and autocompleteTorrents() methods are excluded because
 * they depend on MeiliSearch, Network::ipLocationWithContext(), and
 * UserDisplay rendering that require full web request context.
 */
final class TorrentAjaxRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentAjaxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('files')->delete();
        DB::table('snatched')->delete();
        DB::table('peers')->delete();
        DB::table('suggest')->delete();
        DB::table('torrents')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new TorrentAjaxRepository;
    }

    public function test_file_list_returns_empty_when_no_files(): void
    {
        $torrentId = $this->createTorrent($this->createUser());

        $result = $this->repository->fileList($torrentId);

        $this->assertTrue($result->isEmpty());
    }

    public function test_file_list_returns_files_ordered_by_id(): void
    {
        $torrentId = $this->createTorrent($this->createUser());
        DB::table('files')->insert([
            ['torrent' => $torrentId, 'filename' => 'file2.txt', 'size' => 200],
            ['torrent' => $torrentId, 'filename' => 'file1.txt', 'size' => 100],
        ]);

        $result = $this->repository->fileList($torrentId)->all();

        $this->assertCount(2, $result);
        $this->assertSame('file2.txt', $result[0]->filename);
        $this->assertSame('file1.txt', $result[1]->filename);
    }

    public function test_file_list_only_returns_files_for_given_torrent(): void
    {
        $torrentId1 = $this->createTorrent($this->createUser());
        $torrentId2 = $this->createTorrent($this->createUser());
        DB::table('files')->insert([
            ['torrent' => $torrentId1, 'filename' => 'torrent1.txt', 'size' => 100],
            ['torrent' => $torrentId2, 'filename' => 'torrent2.txt', 'size' => 200],
        ]);

        $result = $this->repository->fileList($torrentId1)->all();

        $this->assertCount(1, $result);
        $this->assertSame('torrent1.txt', $result[0]->filename);
    }

    public function test_search_suggest_returns_empty_result_for_empty_string(): void
    {
        $result = $this->repository->searchSuggest('');

        $this->assertSame(['', [], []], $result);
    }

    public function test_search_suggest_returns_matching_keywords(): void
    {
        $searchstr = 'test';
        Cache::forget('searchsuggest_'.md5($searchstr));
        DB::table('suggest')->insert([
            ['keywords' => 'test movie', 'userid' => 1, 'adddate' => now()->toDateTimeString()],
            ['keywords' => 'test movie', 'userid' => 2, 'adddate' => now()->toDateTimeString()],
            ['keywords' => 'test anime', 'userid' => 3, 'adddate' => now()->toDateTimeString()],
            ['keywords' => 'other', 'userid' => 4, 'adddate' => now()->toDateTimeString()],
        ]);

        $result = $this->repository->searchSuggest($searchstr);

        $this->assertSame($searchstr, $result[0]);
        $this->assertCount(2, $result[1]);
        $this->assertContains('test movie', $result[1]);
        $this->assertContains('test anime', $result[1]);
        $this->assertContains(2, $result[2]);
        $this->assertContains(1, $result[2]);
    }

    public function test_search_suggest_returns_cached_result_on_second_call(): void
    {
        $searchstr = 'cached';
        Cache::forget('searchsuggest_'.md5($searchstr));
        DB::table('suggest')->insert([
            ['keywords' => 'cached query', 'userid' => 1, 'adddate' => now()->toDateTimeString()],
        ]);

        $first = $this->repository->searchSuggest($searchstr);
        $this->assertCount(1, $first[1]);

        // Delete the DB rows — second call should use cache
        DB::table('suggest')->delete();

        $second = $this->repository->searchSuggest($searchstr);
        $this->assertSame($first, $second);
    }

    public function test_search_suggest_returns_empty_when_no_matches(): void
    {
        $searchstr = 'nomatch';
        Cache::forget('searchsuggest_'.md5($searchstr));

        $result = $this->repository->searchSuggest($searchstr);

        $this->assertSame([$searchstr, [], []], $result);
    }

    public function test_snatch_list_returns_empty_when_no_snatches(): void
    {
        $torrentId = $this->createTorrent($this->createUser());

        $result = $this->repository->snatchList($torrentId);

        $this->assertSame($torrentId, $result['id']);
        $this->assertSame(0, $result['count']);
        $this->assertTrue($result['snatchedRows']->isEmpty());
    }

    public function test_snatch_list_returns_finished_snatches(): void
    {
        $userId = $this->createUser();
        $torrentId = $this->createTorrent($userId);
        DB::table('snatched')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'finished' => 1,
            'completedat' => now()->toDateTimeString(),
            'uploaded' => 1024,
        ]);

        $result = $this->repository->snatchList($torrentId);

        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['snatchedRows']);
    }

    public function test_snatch_list_excludes_unfinished_snatches(): void
    {
        $userId = $this->createUser();
        $torrentId = $this->createTorrent($userId);
        DB::table('snatched')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'finished' => 0,
        ]);

        $result = $this->repository->snatchList($torrentId);

        $this->assertSame(0, $result['count']);
    }

    public function test_user_torrent_list_returns_empty_for_invalid_type(): void
    {
        $userId = $this->createUser();

        $result = $this->repository->userTorrentList($userId, 'invalid', 1);

        $this->assertSame($userId, $result['id']);
        $this->assertSame('invalid', $result['type']);
        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['count']);
        $this->assertSame(0, $result['total_size']);
    }

    public function test_user_torrent_list_uploaded_returns_torrents_owned_by_user(): void
    {
        $userId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $torrentId = $this->createTorrent($userId, ['category' => $categoryId, 'name' => 'Uploaded Torrent']);

        $result = $this->repository->userTorrentList($userId, 'uploaded', 1);

        $this->assertGreaterThan(0, $result['count']);
        $found = false;
        foreach ($result['rows'] as $row) {
            if ((int) $row['torrent'] === $torrentId) {
                $found = true;
                $this->assertSame('Uploaded Torrent', $row['torrentname']);
            }
        }
        $this->assertTrue($found);
    }

    public function test_user_torrent_list_uploaded_excludes_other_users_torrents(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $this->createTorrent($otherUserId, ['category' => $categoryId, 'name' => 'Other User Torrent']);

        $result = $this->repository->userTorrentList($userId, 'uploaded', 1);

        $this->assertSame(0, $result['count']);
    }

    public function test_user_torrent_list_uploaded_filters_anonymous_for_other_viewers(): void
    {
        $ownerId = $this->createUser();
        $viewerId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $this->createTorrent($ownerId, ['category' => $categoryId, 'anonymous' => 1, 'name' => 'Anon Torrent']);
        /** @var User $viewer */
        $viewer = User::query()->findOrFail($viewerId);

        $result = $this->repository->userTorrentList($ownerId, 'uploaded', 1, $viewer);

        // Anonymous torrents should be hidden from other users
        $this->assertSame(0, $result['count']);
    }

    public function test_user_torrent_list_uploaded_shows_anonymous_to_owner(): void
    {
        $ownerId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $torrentId = $this->createTorrent($ownerId, ['category' => $categoryId, 'anonymous' => 1, 'name' => 'My Anon Torrent']);
        /** @var User $owner */
        $owner = User::query()->findOrFail($ownerId);

        $result = $this->repository->userTorrentList($ownerId, 'uploaded', 1, $owner);

        $this->assertGreaterThan(0, $result['count']);
        $found = false;
        foreach ($result['rows'] as $row) {
            if ((int) $row['torrent'] === $torrentId) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function test_user_torrent_list_completed_returns_finished_snatches(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $torrentId = $this->createTorrent($otherUserId, ['category' => $categoryId, 'name' => 'Completed Torrent']);
        DB::table('snatched')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'finished' => 1,
            'completedat' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->userTorrentList($userId, 'completed', 1);

        $this->assertGreaterThan(0, $result['count']);
    }

    public function test_user_torrent_list_completed_excludes_own_torrents(): void
    {
        $userId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $torrentId = $this->createTorrent($userId, ['category' => $categoryId]);
        DB::table('snatched')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'finished' => 1,
            'completedat' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->userTorrentList($userId, 'completed', 1);

        $this->assertSame(0, $result['count']);
    }

    public function test_user_torrent_list_incomplete_returns_unfinished_snatches(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $categoryId = $this->ensureCategory();
        $torrentId = $this->createTorrent($otherUserId, ['category' => $categoryId, 'name' => 'Incomplete Torrent']);
        DB::table('snatched')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'finished' => 0,
        ]);

        $result = $this->repository->userTorrentList($userId, 'incomplete', 1);

        $this->assertGreaterThan(0, $result['count']);
    }

    private function createUser(): int
    {
        /** @var User $user */
        $user = User::factory()->create();

        return $user->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTorrent(int $ownerId, array $overrides = []): int
    {
        return (int) DB::table('torrents')->insertGetId(array_merge([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'anonymous' => 0,
            'added' => now()->toDateTimeString(),
            'seeders' => 0,
            'leechers' => 0,
        ], $overrides));
    }

    private function ensureCategory(): int
    {
        return (int) DB::table('categories')->insertGetId([
            'mode' => 1,
            'class_name' => 'test',
            'name' => 'Test Category',
            'image' => 'test.gif',
            'sort_index' => 0,
        ]);
    }
}
