<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Cleanup\Tasks;

use App\Models\User;
use App\Services\Cleanup\Tasks\PeerCleanupTask;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * M6: pruned peers no longer drift the denormalized torrents.seeders/
 * leechers counters — cleanup owns the resync, not the read path.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PeerCleanupTaskTest extends TestCase
{
    private PeerCleanupTask $task;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('peers')->delete();
        DB::table('torrents')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->userId = (int) User::factory()->create()->id;
        $this->task = app(PeerCleanupTask::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_prune_peers_resyncs_counters_of_affected_torrents(): void
    {
        $torrentId = $this->insertTorrent(['seeders' => 5, 'leechers' => 4]);
        $untouchedId = $this->insertTorrent(['seeders' => 7, 'leechers' => 8]);
        $stale = now()->subDays(2)->toDateTimeString();
        $fresh = now()->toDateTimeString();

        // Two dead peers on torrent 1, one live peer on torrent 2.
        $this->insertPeer($torrentId, 1, $stale);
        $this->insertPeer($torrentId, 0, $stale);
        $this->insertPeer($untouchedId, 1, $fresh);

        $this->task->prunePeers();

        // Dead peers removed, live peer kept.
        $this->assertSame(0, (int) DB::table('peers')->where('torrent', $torrentId)->count());
        $this->assertSame(1, (int) DB::table('peers')->where('torrent', $untouchedId)->count());

        // Affected torrent's counters were resynced to the real live counts.
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrentId)->value('seeders'));
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrentId)->value('leechers'));

        // Unrelated torrent was not rewritten.
        $this->assertSame(7, (int) DB::table('torrents')->where('id', $untouchedId)->value('seeders'));
        $this->assertSame(8, (int) DB::table('torrents')->where('id', $untouchedId)->value('leechers'));
    }

    /** @param array<string, mixed> $overrides */
    private function insertTorrent(array $overrides): int
    {
        return (int) DB::table('torrents')->insertGetId(array_merge([
            'name' => 'Cleanup Test '.uniqid(),
            'filename' => 'cleanup.torrent',
            'save_as' => 'cleanup',
            'category' => 1,
            'size' => 1024,
            'type' => 0,
            'numfiles' => 1,
            'owner' => $this->userId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
            'seeders' => 0,
            'leechers' => 0,
        ], $overrides));
    }

    private function insertPeer(int $torrentId, int $seeder, string $lastAction): void
    {
        DB::table('peers')->insert([
            'torrent' => $torrentId,
            'peer_id' => '-qB0001-'.random_bytes(12),
            'userid' => $this->userId,
            'seeder' => $seeder,
            'ip' => '127.0.0.1',
            'port' => 51413,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => now()->toDateTimeString(),
            'last_action' => $lastAction,
            'connectable' => 1,
        ]);
    }
}
