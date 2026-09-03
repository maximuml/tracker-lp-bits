<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\TorrentListingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentListingRepository.
 *
 * Covers getCount(), getList(), getHotSearch() and cleanupSuggest().
 */
final class TorrentListingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentListingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('suggest')->truncate();
        DB::table('torrents')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new TorrentListingRepository;
    }

    public function test_get_count_returns_zero_when_no_torrents(): void
    {
        $this->assertSame(0, $this->repository->getCount([]));
    }

    public function test_get_count_counts_all_torrents(): void
    {
        $this->createTorrent(1);
        $this->createTorrent(1);

        $this->assertSame(2, $this->repository->getCount([]));
    }

    public function test_get_count_with_where_filter(): void
    {
        $this->createTorrent(1, 'Alpha');
        $this->createTorrent(1, 'Beta');

        $count = $this->repository->getCount([
            'where' => 'WHERE torrents.name LIKE ?',
            'where_bindings' => ['Alpha%'],
        ]);

        $this->assertSame(1, $count);
    }

    public function test_get_count_with_join_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id);

        $count = $this->repository->getCount(['join_users' => true]);

        $this->assertSame(1, $count);
    }

    public function test_get_list_returns_empty_when_no_torrents(): void
    {
        $result = $this->repository->getList($this->baseOptions());

        $this->assertSame([], $result);
    }

    public function test_get_list_returns_rows_with_search_box_id(): void
    {
        $id = $this->createTorrent(1, 'MyTorrent');

        $result = $this->repository->getList($this->baseOptions());

        $this->assertCount(1, $result);
        $this->assertSame($id, (int) $result[0]['id']);
        $this->assertSame('MyTorrent', $result[0]['name']);
        $this->assertSame(7, (int) $result[0]['search_box_id']);
    }

    public function test_get_list_respects_offset_and_limit(): void
    {
        $this->createTorrent(1, 'T1');
        $this->createTorrent(1, 'T2');
        $this->createTorrent(1, 'T3');

        $options = $this->baseOptions();
        $options['offset'] = 1;
        $options['limit'] = 1;
        $options['order_by'] = [['id', 'asc']];

        $result = $this->repository->getList($options);

        $this->assertCount(1, $result);
    }

    public function test_get_list_with_order_by_desc(): void
    {
        $first = $this->createTorrent(1, 'First');
        $second = $this->createTorrent(1, 'Second');

        $options = $this->baseOptions();
        $options['limit'] = 1;
        $options['order_by'] = [['id', 'desc']];

        $result = $this->repository->getList($options);

        $this->assertCount(1, $result);
        $this->assertSame(max($first, $second), (int) $result[0]['id']);
    }

    public function test_get_list_with_join_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id, 'Owned');

        $options = $this->baseOptions();
        $options['fields'] = ['torrents.id', 'torrents.name', 'users.username'];
        $options['join_users'] = true;

        $result = $this->repository->getList($options);

        $this->assertCount(1, $result);
        $this->assertSame($user->username, $result[0]['username']);
    }

    public function test_get_hot_search_returns_empty_when_no_records(): void
    {
        $this->assertSame([], $this->repository->getHotSearch());
    }

    public function test_get_hot_search_returns_aggregated_counts(): void
    {
        $recent = now()->toDateTimeString();
        DB::table('suggest')->insert([
            ['keywords' => 'linux', 'userid' => 1, 'adddate' => $recent],
            ['keywords' => 'linux', 'userid' => 2, 'adddate' => $recent],
            ['keywords' => 'linux', 'userid' => 1, 'adddate' => $recent],
            ['keywords' => 'movie', 'userid' => 3, 'adddate' => $recent],
        ]);

        $result = $this->repository->getHotSearch();
        $keyed = [];
        foreach ($result as $row) {
            $keyed[$row['keywords']] = (int) $row['count'];
        }

        $this->assertSame(2, $keyed['linux']);
        $this->assertSame(1, $keyed['movie']);
    }

    public function test_get_hot_search_excludes_old_records(): void
    {
        $old = now()->subDays(10)->toDateTimeString();
        DB::table('suggest')->insert([
            ['keywords' => 'old', 'userid' => 1, 'adddate' => $old],
        ]);

        $this->assertSame([], $this->repository->getHotSearch());
    }

    public function test_cleanup_suggest_deletes_old_records(): void
    {
        $recent = now()->toDateTimeString();
        $old = now()->subDays(10)->toDateTimeString();
        DB::table('suggest')->insert([
            ['keywords' => 'recent', 'userid' => 1, 'adddate' => $recent],
            ['keywords' => 'old', 'userid' => 1, 'adddate' => $old],
        ]);

        $this->repository->cleanupSuggest();

        $this->assertSame(1, DB::table('suggest')->count());
        $remaining = DB::table('suggest')->first();
        $this->assertNotNull($remaining);
        $this->assertSame('recent', $remaining->keywords);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function baseOptions(): array
    {
        return [
            'fields' => ['torrents.id', 'torrents.name'],
            'search_box_id' => 7,
            'offset' => 0,
            'limit' => 25,
            'order_by' => [['id', 'asc']],
        ];
    }

    private function createTorrent(int $ownerId, string $name = 'Test Torrent'): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => $name,
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
            'added' => now()->toDateTimeString(),
        ]);
    }
}
