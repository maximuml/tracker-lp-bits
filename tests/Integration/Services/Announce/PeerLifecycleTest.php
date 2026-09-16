<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Announce\PeerLifecycle;
use App\Support\Cache;
use App\Support\LegacyDb;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use stdClass;
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

    protected function tearDown(): void
    {
        Cache::clearSettings();
        parent::tearDown();
    }

    private const PEER_ID = '-qB4500-abcdefghijk0';

    private function makeDto(
        ?string $event = null,
        int $left = 500,
        int $uploaded = 100,
        int $downloaded = 50,
        string $peerId = self::PEER_ID,
        string $ip = '10.0.0.1',
        ?string $ipv6 = null,
    ): AnnounceRequestDto {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary($peerId),
            port: 6881,
            uploaded: $uploaded,
            downloaded: $downloaded,
            left: $left,
            event: $event,
            numWant: 50,
            compact: false,
            ipv4: $ip,
            ipv6: $ipv6,
            ip: $ip,
            userAgent: 'qBittorrent/4.5.0',
        );
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);

        return $user;
    }

    private function makeTorrent(User $user): Torrent
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000]);

        return $torrent;
    }

    private function peerRow(Torrent $torrent, User $user): stdClass
    {
        $row = DB::table('peers')->where('torrent', $torrent->id)->where('userid', $user->id)->first();
        $this->assertNotNull($row);

        return $row;
    }

    private function snatchRow(Torrent $torrent, User $user): stdClass
    {
        $row = DB::table('snatched')->where('torrentid', $torrent->id)->where('userid', $user->id)->first();
        $this->assertNotNull($row);

        return $row;
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

    private function insertPeer(User $user, Torrent $torrent, int $seeder = 0, string $peerId = self::PEER_ID, string $ip = '10.0.0.1'): int
    {
        return (int) DB::table('peers')->insertGetId([
            'torrent' => $torrent->id,
            'userid' => $user->id,
            'peer_id' => $peerId,
            'ip' => $ip,
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 500,
            'seeder' => $seeder,
            'started' => $this->dt(),
            'last_action' => $this->dt(),
            'prev_action' => $this->dt(),
            'agent' => 'qBittorrent/4.5.0',
            'passkey' => $user->passkey,
        ]);
    }

    private function insertSnatch(User $user, Torrent $torrent, int $finished = 0): int
    {
        return (int) DB::table('snatched')->insertGetId([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '10.0.0.1',
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 500,
            'finished' => $finished,
            'startdat' => $this->dt(),
            'last_action' => $this->dt(),
        ]);
    }

    public function test_process_inserts_new_peer_and_snatch(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $this->assertNull($lifecycle->findSelf());

        $result = $lifecycle->process(100, 50, 'leechtime', 60, 0);

        $peer = $this->peerRow($torrent, $user);
        $this->assertSame(100, (int) $peer->uploaded);
        $this->assertSame(50, (int) $peer->downloaded);
        $this->assertSame(500, (int) $peer->to_go);
        $this->assertSame(6881, (int) $peer->port);
        $this->assertSame(0, (int) $peer->seeder);
        $this->assertSame(50, (int) $peer->downloadoffset);
        $this->assertSame(100, (int) $peer->uploadoffset);
        $this->assertSame('10.0.0.1', $peer->ipv4);
        $this->assertSame('qBittorrent/4.5.0', $peer->agent);

        $snatch = $this->snatchRow($torrent, $user);
        $this->assertSame(100, (int) $snatch->uploaded);
        $this->assertSame(50, (int) $snatch->downloaded);
        $this->assertSame(500, (int) $snatch->to_go);

        $this->assertArrayHasKey('leechers', $result->torrentUpdate);
        $this->assertArrayNotHasKey('seeders', $result->torrentUpdate);
        $this->assertNotNull($result->snatchInfo);
    }

    public function test_process_inserts_seeder_peer(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);

        $lifecycle = new PeerLifecycle($this->makeDto(null, 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $result = $lifecycle->process(100, 0, 'seedtime', 60, 0);

        $peer = $this->peerRow($torrent, $user);
        $this->assertSame(1, (int) $peer->seeder);
        $this->assertSame(0, (int) $peer->to_go);
        $this->assertArrayHasKey('seeders', $result->torrentUpdate);
        $this->assertArrayNotHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_skips_insert_for_stopped_event_without_peer(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);

        $lifecycle = new PeerLifecycle($this->makeDto('stopped'), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(0, 0, 'leechtime', 0, 0);

        $this->assertSame(0, DB::table('peers')->where('torrent', $torrent->id)->count());
        $this->assertSame(0, DB::table('snatched')->where('torrentid', $torrent->id)->count());
    }

    public function test_process_updates_existing_peer_and_snatch(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent);
        $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto(null, 400, 600, 300), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $this->assertNotNull($lifecycle->findSelf());
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 300, 'leechtime', 60, 0);

        $peer = $this->peerRow($torrent, $user);
        $this->assertSame(600, (int) $peer->uploaded);
        $this->assertSame(300, (int) $peer->downloaded);
        $this->assertSame(400, (int) $peer->to_go);
        $this->assertSame(0, (int) $peer->seeder);

        $snatch = $this->snatchRow($torrent, $user);
        $this->assertSame(600, (int) $snatch->uploaded);
        $this->assertSame(300, (int) $snatch->downloaded);
        $this->assertSame(400, (int) $snatch->to_go);
        $this->assertSame(60, (int) $snatch->leechtime);

        // Seeder flag unchanged → no counter migration.
        $this->assertArrayNotHasKey('seeders', $result->torrentUpdate);
        $this->assertArrayNotHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_update_migrates_leecher_to_seeder(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent, seeder: 0);
        $this->insertSnatch($user, $torrent);

        // left=0 → isSeeder() → seeder flag flips to 1.
        $lifecycle = new PeerLifecycle($this->makeDto(null, 0, 600, 500), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'seedtime', 60, 0);

        $peer = $this->peerRow($torrent, $user);
        $this->assertSame(1, (int) $peer->seeder);
        $this->assertArrayHasKey('seeders', $result->torrentUpdate);
        $this->assertArrayHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_completed_event_marks_snatch_finished(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent);
        $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto('completed', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'leechtime', 60, 0);

        $snatch = $this->snatchRow($torrent, $user);
        $this->assertSame(1, (int) $snatch->finished);
        $this->assertNotNull($snatch->completedat);
        $this->assertArrayHasKey('times_completed', $result->torrentUpdate);
        $this->assertNotNull($this->peerRow($torrent, $user)->finishedat);
    }

    public function test_process_completed_is_idempotent_when_already_finished(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent);
        $this->insertSnatch($user, $torrent, finished: 1);

        $lifecycle = new PeerLifecycle($this->makeDto('completed', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'leechtime', 60, 0);

        $this->assertArrayNotHasKey('times_completed', $result->torrentUpdate);
    }

    public function test_process_stopped_deletes_leecher_peer(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent);
        $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto('stopped', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'leechtime', 60, 0);

        $this->assertSame(0, DB::table('peers')->where('torrent', $torrent->id)->count());
        $this->assertArrayHasKey('leechers', $result->torrentUpdate);
        $this->assertArrayNotHasKey('seeders', $result->torrentUpdate);
    }

    public function test_process_stopped_deletes_seeder_peer(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent, seeder: 1);
        $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto('stopped', 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $result = $lifecycle->process(600, 500, 'seedtime', 60, 0);

        $this->assertSame(0, DB::table('peers')->where('torrent', $torrent->id)->count());
        $this->assertArrayHasKey('seeders', $result->torrentUpdate);
        $this->assertArrayNotHasKey('leechers', $result->torrentUpdate);
    }

    public function test_process_ignores_duplicate_peer_insert(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(100, 50, 'leechtime', 60, 0);

        $lifecycle2 = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle2->process(100, 50, 'leechtime', 60, 0);

        $this->assertSame(1, DB::table('peers')->where('torrent', $torrent->id)->where('userid', $user->id)->count());
    }

    public function test_new_peer_updates_existing_snatch_row(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $snatchId = $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto(null, 200), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(100, 50, 'leechtime', 60, 0);

        $this->assertSame(1, DB::table('snatched')->where('torrentid', $torrent->id)->where('userid', $user->id)->count());
        $row = DB::table('snatched')->where('id', $snatchId)->first();
        $this->assertNotNull($row);
        $this->assertSame(200, (int) $row->to_go);
    }

    public function test_second_leech_location_is_rejected(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent, seeder: 0, peerId: '-qB4500-zzzzzzzzzzz0', ip: '10.0.0.2');

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());

        $this->expectException(TrackerException::class);
        $lifecycle->process(100, 50, 'leechtime', 60, 0);
    }

    public function test_fourth_seed_location_is_rejected(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        foreach (['10.0.0.2', '10.0.0.3', '10.0.0.4'] as $i => $ip) {
            $this->insertPeer($user, $torrent, seeder: 1, peerId: '-qB4500-zzzzzzzzzzz'.$i, ip: $ip);
        }

        $lifecycle = new PeerLifecycle($this->makeDto(null, 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());

        $this->expectException(TrackerException::class);
        $lifecycle->process(100, 0, 'seedtime', 60, 0);
    }

    public function test_find_self_returns_peer_with_computed_fields(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $peerId = $this->insertPeer($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto(), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $self = $lifecycle->findSelf();

        $this->assertNotNull($self);
        $this->assertSame($peerId, (int) $self['id']);
        $this->assertArrayHasKey('announcetime', $self);
        $this->assertArrayHasKey('prevts', $self);
        $this->assertGreaterThanOrEqual(0, $self['announcetime']);
    }

    public function test_new_peer_records_ipv6(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);

        $lifecycle = new PeerLifecycle($this->makeDto(ipv6: '2001:db8::1'), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->process(100, 50, 'leechtime', 60, 0);

        $peer = $this->peerRow($torrent, $user);
        $this->assertSame('2001:db8::1', $peer->ipv6);
    }

    public function test_snatch_update_accumulates_no_seeder_leech_time(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent);
        $this->insertSnatch($user, $torrent);

        $lifecycle = new PeerLifecycle($this->makeDto(null, 400, 600, 300), $this->torrentRow($torrent), $this->userRow($user), $this->dt());
        $lifecycle->findSelf();
        $lifecycle->setSnatchInfo(LegacyDb::snatchInfo($torrent->id, $user->id));
        $lifecycle->process(600, 300, 'leechtime', 60, 120);

        $snatch = $this->snatchRow($torrent, $user);
        $this->assertSame(120, (int) $snatch->leech_time_no_seeder);
    }

    public function test_same_ip_seeder_is_warned(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user);
        $this->insertPeer($user, $torrent, seeder: 1, peerId: '-qB4500-zzzzzzzzzzz0', ip: '10.0.0.1');

        $lifecycle = new PeerLifecycle($this->makeDto(null, 0), $this->torrentRow($torrent), $this->userRow($user), $this->dt());

        $this->expectException(TrackerWarningException::class);
        $lifecycle->process(100, 0, 'seedtime', 60, 0);
    }
}
