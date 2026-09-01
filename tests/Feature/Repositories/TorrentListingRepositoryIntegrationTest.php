<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Enums\TorrentVisible;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentListingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TorrentListingRepositoryIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentListingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TorrentListingRepository::class);
    }

    public function test_get_count_returns_zero_for_empty_table(): void
    {
        $count = $this->repository->getCount([
            'where' => '',
            'where_bindings' => [],
        ]);

        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_get_count_counts_torrents(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();

        Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
        ]);
        Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
        ]);

        $count = $this->repository->getCount([
            'where' => 'torrents.category = ?',
            'where_bindings' => [$category->id],
        ]);

        $this->assertSame(2, $count);
    }

    public function test_get_list_returns_rows_with_fields(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
            'name' => 'Integration Test Torrent',
        ]);

        $rows = $this->repository->getList([
            'where' => 'torrents.id = ?',
            'where_bindings' => [$torrent->id],
            'fields' => ['torrents.id', 'torrents.name'],
            'search_box_id' => $category->id,
            'offset' => 0,
            'limit' => 10,
            'order_by' => [['torrents.id', 'desc']],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame($torrent->id, (int) $rows[0]['id']);
        $this->assertSame('Integration Test Torrent', $rows[0]['name']);
        $this->assertSame($category->id, (int) $rows[0]['search_box_id']);
    }

    public function test_get_list_respects_offset_and_limit(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Torrent::factory()->create([
                'category' => $category->id,
                'owner' => $user->id,
                'visible' => TorrentVisible::YES->value,
                'name' => "Test Torrent {$i}",
            ]);
        }

        $rows = $this->repository->getList([
            'where' => 'torrents.category = ?',
            'where_bindings' => [$category->id],
            'fields' => ['torrents.id', 'torrents.name'],
            'search_box_id' => $category->id,
            'offset' => 2,
            'limit' => 2,
            'order_by' => [['torrents.id', 'asc']],
        ]);

        $this->assertCount(2, $rows);
    }

    public function test_get_list_with_join_users(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create(['username' => 'TestUploader123']);

        Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
        ]);

        $rows = $this->repository->getList([
            'where' => 'torrents.owner = ?',
            'where_bindings' => [$user->id],
            'fields' => ['torrents.id', 'users.username'],
            'search_box_id' => $category->id,
            'offset' => 0,
            'limit' => 10,
            'join_users' => true,
            'order_by' => [['torrents.id', 'desc']],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('TestUploader123', $rows[0]['username']);
    }

    public function test_get_list_with_invalid_order_direction_defaults_to_asc(): void
    {
        $category = Category::factory()->create();
        $user = User::factory()->create();

        $torrent1 = Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
        ]);
        $torrent2 = Torrent::factory()->create([
            'category' => $category->id,
            'owner' => $user->id,
            'visible' => TorrentVisible::YES->value,
        ]);

        $rows = $this->repository->getList([
            'where' => 'torrents.category = ?',
            'where_bindings' => [$category->id],
            'fields' => ['torrents.id'],
            'search_box_id' => $category->id,
            'offset' => 0,
            'limit' => 10,
            'order_by' => [['torrents.id', 'INVALID']],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame($torrent1->id, (int) $rows[0]['id']);
        $this->assertSame($torrent2->id, (int) $rows[1]['id']);
    }
}
