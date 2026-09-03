<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\AdminStatsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for AdminStatsRepository.
 *
 * Covers stats() and allagents() public methods.
 */
final class AdminStatsRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AdminStatsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('peers')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::table('categories')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new AdminStatsRepository;
    }

    public function test_stats_returns_zero_counts_when_no_records(): void
    {
        $result = $this->repository->stats('name', 'name');

        $this->assertSame(0, $result['n_tor']);
        $this->assertSame(0, $result['n_peers']);
        $this->assertSame('name', $result['uporder']);
        $this->assertSame('name', $result['catorder']);
        $this->assertSame([], $result['cats']->all());
    }

    public function test_stats_counts_torrents_and_peers(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrentId = $this->createTorrent($user->id);
        $this->createPeer($torrentId, $user->id, 'uTorrent');

        $result = $this->repository->stats('name', 'name');

        $this->assertSame(1, $result['n_tor']);
        $this->assertSame(1, $result['n_peers']);
    }

    public function test_stats_returns_empty_cats_when_no_torrents(): void
    {
        $result = $this->repository->stats('name', 'name');

        $this->assertSame([], $result['cats']->all());
    }

    public function test_stats_returns_category_activity_when_torrents_exist(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'name' => 'Movies', 'mode' => 1, 'class_name' => 'movies', 'image' => 'm.gif'],
            ['id' => 2, 'name' => 'Music', 'mode' => 1, 'class_name' => 'music', 'image' => 'mu.gif'],
        ]);
        /** @var User $user */
        $user = User::factory()->create();
        $this->createTorrent($user->id, 1);
        $this->createTorrent($user->id, 2);

        $result = $this->repository->stats('name', 'name');

        $cats = $result['cats']->keyBy('name');
        $movies = $cats->get('Movies');
        $music = $cats->get('Music');
        $this->assertNotNull($movies);
        $this->assertNotNull($music);
        $this->assertSame(1, (int) $movies->n_t);
        $this->assertSame(1, (int) $music->n_t);
    }

    public function test_stats_uploader_activity_includes_uploaders(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create(['class' => 3]);
        /** @var User $admin */
        $admin = User::factory()->create(['class' => 5]);
        $this->createTorrent($uploader->id);
        $this->createTorrent($admin->id);

        $result = $this->repository->stats('name', 'name');

        $upers = $result['upers']->keyBy('name');
        $this->assertTrue($upers->has($uploader->username));
        $this->assertTrue($upers->has($admin->username));
        $uploaderRow = $upers->get($uploader->username);
        $adminRow = $upers->get($admin->username);
        $this->assertNotNull($uploaderRow);
        $this->assertNotNull($adminRow);
        $this->assertSame(1, (int) $uploaderRow->n_t);
        $this->assertSame(1, (int) $adminRow->n_t);
    }

    public function test_allagents_returns_empty_when_no_peers(): void
    {
        $this->assertSame([], $this->repository->allagents()->all());
    }

    public function test_allagents_groups_by_agent_and_counts(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        /** @var User $user3 */
        $user3 = User::factory()->create();
        $torrentId = $this->createTorrent($user1->id);
        $this->createPeer($torrentId, $user1->id, 'uTorrent');
        $this->createPeer($torrentId, $user2->id, 'uTorrent');
        $this->createPeer($torrentId, $user3->id, 'Transmission');

        $result = $this->repository->allagents()->keyBy('agent');

        $uTorrent = $result->get('uTorrent');
        $transmission = $result->get('Transmission');
        $this->assertNotNull($uTorrent);
        $this->assertNotNull($transmission);
        $this->assertSame(2, (int) $uTorrent->counts);
        $this->assertSame(1, (int) $transmission->counts);
    }

    private function createTorrent(int $ownerId, int $category = 1): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent '.$ownerId.'-'.$category,
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => $category,
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

    private function createPeer(int $torrentId, int $userId, string $agent): void
    {
        DB::table('peers')->insert([
            'torrent' => $torrentId,
            'userid' => $userId,
            'agent' => $agent,
            'peer_id' => random_bytes(20),
            'ip' => '1.1.1.1',
            'port' => 1,
            'seeder' => 1,
            'connectable' => 1,
            'passkey' => str_pad('p', 32, '0'),
        ]);
    }
}
