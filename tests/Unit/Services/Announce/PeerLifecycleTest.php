<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Announce\PeerLifecycle;
use App\Support\LegacyDb;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * PeerLifecycle: new-peer insert, update, and stopped transitions against
 * the real peers/snatched tables.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PeerLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        defined('TIMENOW') || define('TIMENOW', time());
    }

    private function makeDto(?string $event = null, int $left = 500, int $uploaded = 100, int $downloaded = 50): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary('-qB4500-'.str_repeat("\x01", 12)),
            port: 6881,
            uploaded: $uploaded,
            downloaded: $downloaded,
            left: $left,
            event: $event,
            numWant: 50,
            compact: false,
            ipv4: '10.0.0.1',
            ipv6: null,
            ip: '10.0.0.1',
            userAgent: 'qBittorrent/4.5.0',
        );
    }

    /** @return array<string, mixed> */
    private function torrentRow(Torrent $torrent): array
    {
        $row = $torrent->getAttributes();
        $row['ts'] = time();

        return $row;
    }

    /** @return array<string, mixed> */
    private function userRow(User $user): array
    {
        return $user->getAttributes();
    }

    private function dt(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function newPeer(User $user, Torrent $torrent): int
    {
        return (int) DB::table('peers')->insertGetId([
            'torrent' => $torrent->id,
            'userid' => $user->id,
            'peer_id' => '-qB4500-'.str_repeat("\x01", 12),
            'ip' => '10.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 500,
            'seeder' => 0,
            'started' => $this->dt(),
            'last_action' => $this->dt(),
            'prev_action' => $this->dt(),
            'agent' => 'qBittorrent/4.5.0',
            'passkey' => $user->passkey,
        ]);
    }

    public function test_process_inserts_new_peer_and_snatch(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'leechers' => 0]);

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $this->assertNull($lifecycle->findSelf());

        $result = $lifecycle->process(100, 50, 'leechtime', 60, 0);

        $this->assertSame(1, DB::table('peers')->where('torrent', $torrent->id)->where('userid', $user->id)->count());
        $this->assertSame(1, DB::table('snatched')->where('torrentid', $torrent->id)->where('userid', $user->id)->count());
        $this->assertArrayHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_skips_insert_for_stopped_event_without_peer(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000]);

        $lifecycle = new PeerLifecycle($this->makeDto('stopped'), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(0, 0, 'leechtime', 0, 0);

        $this->assertSame(0, DB::table('peers')->where('torrent', $torrent->id)->count());
    }

    public function test_process_updates_existing_peer_and_snatch(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000]);
        $this->newPeer($user, $torrent);
        DB::table('snatched')->insert([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '10.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 500,
            'startdat' => $this->dt(),
            'last_action' => $this->dt(),
        ]);

        $lifecycle = new PeerLifecycle($this->makeDto(null, 400, 600, 300), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $this->assertNotNull($lifecycle->findSelf());
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $lifecycle->process(600, 300, 'leechtime', 60, 0);

        $peer = DB::table('peers')->where('torrent', $torrent->id)->where('userid', $user->id)->first();
        $this->assertSame(600, (int) $peer->uploaded);
        $this->assertSame(300, (int) $peer->downloaded);
        $this->assertSame(400, (int) $peer->to_go);

        $snatch = DB::table('snatched')->where('torrentid', $torrent->id)->where('userid', $user->id)->first();
        $this->assertSame(600, (int) $snatch->uploaded);
        $this->assertSame(400, (int) $snatch->to_go);
    }

    public function test_process_completed_event_marks_snatch_finished(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'times_completed' => 0]);
        $this->newPeer($user, $torrent);
        DB::table('snatched')->insert([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '10.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 500,
            'startdat' => $this->dt(),
            'last_action' => $this->dt(),
        ]);

        $lifecycle = new PeerLifecycle($this->makeDto('completed', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'leechtime', 60, 0);

        $snatch = DB::table('snatched')->where('torrentid', $torrent->id)->where('userid', $user->id)->first();
        $this->assertSame(1, (int) $snatch->finished);
        $this->assertArrayHasKey('times_completed', $result->torrentUpdate);
    }

    public function test_process_stopped_deletes_peer(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'leechers' => 1]);
        $this->newPeer($user, $torrent);
        DB::table('snatched')->insert([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '10.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'startdat' => $this->dt(),
            'last_action' => $this->dt(),
        ]);

        $lifecycle = new PeerLifecycle($this->makeDto('stopped', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'leechtime', 60, 0);

        $this->assertSame(0, DB::table('peers')->where('torrent', $torrent->id)->count());
        $this->assertArrayHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_ignores_duplicate_peer_insert(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000]);

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(100, 50, 'leechtime', 60, 0);

        // Same peer announces again without findSelf having been called:
        // the explicit exists() guard must keep the row count at 1.
        $lifecycle2 = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle2->process(100, 50, 'leechtime', 60, 0);

        $this->assertSame(1, DB::table('peers')->where('torrent', $torrent->id)->where('userid', $user->id)->count());
    }
}
