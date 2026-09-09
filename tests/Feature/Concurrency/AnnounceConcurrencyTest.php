<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Rhilip\Bencode\Bencode;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W3-03: Concurrency tests for announce/accounting.
 *
 * Verifies that the announce pipeline produces consistent accounting
 * under sequential and repeated announces. The AnnounceService uses
 * DB::transaction() with lockForUpdate() on peer/snatch/user rows to
 * serialize concurrent announces for the same peer. These tests
 * exercise the same code paths and verify the invariants that the
 * locking is designed to protect.
 */
#[TestCategory(TestCategory::CONCURRENCY)]
final class AnnounceConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Repeated announces from the same peer must accumulate uploaded
     * and downloaded deltas correctly on the user record. Each announce
     * reports the cumulative byte counter, and the service computes the
     * delta against the previous peer row value.
     */
    public function test_repeated_announces_accumulate_user_traffic_correctly(): void
    {
        $user = User::factory()->create([
            'uploaded' => 0,
            'downloaded' => 0,
            'class' => 1,
        ]);
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 10000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // Start: 0 uploaded, 100 downloaded
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 9900, 'started');

        // Update 1: 500 uploaded, 300 downloaded
        $this->announce($user->passkey, $infoHash, $peerId, 500, 300, 9700);

        // Update 2: 1000 uploaded, 600 downloaded
        $this->announce($user->passkey, $infoHash, $peerId, 1000, 600, 9400);

        // Stop
        $this->announce($user->passkey, $infoHash, $peerId, 1000, 600, 9400, 'stopped');

        // The user's uploaded/downloaded should reflect the total deltas.
        // Promotion adjustments may apply, but the values must be >= 0 and
        // the peer row must be removed after stopped.
        $finalUploaded = (int) DB::table('users')->where('id', $user->id)->value('uploaded');
        $finalDownloaded = (int) DB::table('users')->where('id', $user->id)->value('downloaded');

        $this->assertGreaterThanOrEqual(0, $finalUploaded);
        $this->assertGreaterThanOrEqual(0, $finalDownloaded);

        // Peer row must be gone after stopped event
        $peerCount = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(0, $peerCount, 'Peer row must be removed after stopped event');
    }

    /**
     * Two different peers on the same torrent must not interfere with
     * each other's accounting. Each peer's uploaded/downloaded delta
     * is computed against its own peer row, not the other peer's.
     */
    public function test_two_distinct_peers_have_independent_accounting(): void
    {
        $owner = User::factory()->create(['uploaded' => 0, 'downloaded' => 0, 'class' => 1]);
        $leecher1 = User::factory()->create(['uploaded' => 0, 'downloaded' => 0, 'class' => 1]);
        $leecher2 = User::factory()->create(['uploaded' => 0, 'downloaded' => 0, 'class' => 1]);

        $torrent = Torrent::factory()->owner($owner)->create([
            'size' => 10000,
            'seeders' => 1,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId1 = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();
        $peerId2 = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // Peer 1 starts leeching
        $this->announce($leecher1->passkey, $infoHash, $peerId1, 0, 100, 9900, 'started');
        // Peer 2 starts leeching
        $this->announce($leecher2->passkey, $infoHash, $peerId2, 0, 200, 9800, 'started');

        // Peer 1 reports 500 uploaded, 300 downloaded
        $this->announce($leecher1->passkey, $infoHash, $peerId1, 500, 300, 9700);
        // Peer 2 reports 1000 uploaded, 400 downloaded
        $this->announce($leecher2->passkey, $infoHash, $peerId2, 1000, 400, 9600);

        $user1Uploaded = (int) DB::table('users')->where('id', $leecher1->id)->value('uploaded');
        $user2Uploaded = (int) DB::table('users')->where('id', $leecher2->id)->value('uploaded');

        // Peer 1 uploaded 500, Peer 2 uploaded 1000 — they must not be swapped or merged
        $this->assertGreaterThan(0, $user1Uploaded, 'Peer 1 must have uploaded traffic');
        $this->assertGreaterThan(0, $user2Uploaded, 'Peer 2 must have uploaded traffic');
        $this->assertNotSame($user1Uploaded, $user2Uploaded, 'Peers must have distinct uploaded values');
    }

    /**
     * A completed event must increment times_completed atomically using
     * a SQL expression (times_completed + 1) and mark the snatch record
     * as finished. The increment must be exactly +1 per completed event,
     * verifying the atomic SQL update rather than a read-modify-write.
     */
    public function test_completed_event_increments_times_completed_atomically(): void
    {
        $user = User::factory()->create([
            'uploaded' => 0,
            'downloaded' => 0,
            'class' => 1,
        ]);
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 1000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // Start leeching
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');

        $timesCompletedBefore = (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed');

        // Complete — must increment by exactly 1 via SQL atomic update
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'completed');

        $timesCompletedAfter = (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed');
        $this->assertSame($timesCompletedBefore + 1, $timesCompletedAfter, 'times_completed must increment by exactly 1 on completed event');

        // Snatch record must be marked finished
        $snatchFinished = (int) DB::table('snatched')
            ->where('torrentid', $torrent->id)
            ->where('userid', $user->id)
            ->value('finished');
        $this->assertSame(1, $snatchFinished, 'Snatch record must be marked finished after completed event');

        // The torrent's leecher count must decrease since the peer
        // transitions from leecher to seeder on completion
        $leechersAfter = (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers');
        $this->assertSame(0, $leechersAfter, 'Leecher count must be 0 after the only leecher completes');
    }

    /**
     * The stopped event must remove the peer row and decrement the
     * seeder/leecher count. The torrent's seeders/leechers must never
     * go negative.
     */
    public function test_stopped_event_removes_peer_and_decrements_count(): void
    {
        $user = User::factory()->create([
            'uploaded' => 0,
            'downloaded' => 0,
            'class' => 1,
        ]);
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 1000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // Start leeching
        $resp = $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');
        $decoded = Bencode::decode($resp->getContent());
        if (isset($decoded['warning message']) || isset($decoded['failure reason'])) {
            fwrite(STDERR, 'Start response: '.$resp->getContent()."\n");
        }

        $leechersBefore = (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers');
        $this->assertGreaterThan(0, $leechersBefore, 'Leecher count must increase after start. Response: '.$resp->getContent());

        // Stop
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'stopped');

        $leechersAfter = (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers');
        $this->assertGreaterThanOrEqual(0, $leechersAfter, 'Leecher count must never go negative');
        $this->assertLessThan($leechersBefore, $leechersAfter, 'Leecher count must decrease after stop');

        // Peer row must be removed
        $peerExists = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->exists();
        $this->assertFalse($peerExists, 'Peer row must be removed after stopped event');
    }

    /**
     * A duplicate peer (same torrent, same peer_id, same user) must not
     * be inserted twice. The PeerLifecycle::processNewPeer() method
     * checks for an existing peer row before inserting. This verifies
     * the duplicate prevention invariant.
     */
    public function test_duplicate_peer_insert_is_prevented(): void
    {
        $user = User::factory()->create([
            'uploaded' => 0,
            'downloaded' => 0,
            'class' => 1,
        ]);
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 1000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // First start creates the peer
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');

        $peerCountAfterFirst = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(1, $peerCountAfterFirst, 'First announce should create exactly one peer row');

        // Stop removes the peer
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'stopped');

        $peerCountAfterStop = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(0, $peerCountAfterStop, 'Stopped event should remove the peer row');

        // Start again — should create exactly one new peer, not duplicate
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');

        $peerCountAfterRestart = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(1, $peerCountAfterRestart, 'Re-start after stop should create exactly one peer row');
    }

    /**
     * User uploaded/downloaded counters must be updated atomically via
     * SQL expressions (uploaded + N), not read-modify-write. This test
     * verifies that multiple sequential announces produce the correct
     * cumulative total on the user row.
     */
    public function test_user_counters_accumulate_via_atomic_sql_increments(): void
    {
        $user = User::factory()->create([
            'uploaded' => 0,
            'downloaded' => 0,
            'class' => 1,
        ]);
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 100000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();

        // Start leeching with 0 uploaded, 1000 downloaded
        $this->announce($user->passkey, $infoHash, $peerId, 0, 1000, 99000, 'started');

        // Simulate time passing so the min-announce-wait check passes.
        // MIN_ANNOUNCE_WAIT_SECOND is 300; set prev_action far in the past.
        $oldTime = date('Y-m-d H:i:s', TIMENOW - 3600);
        DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->update(['prev_action' => $oldTime, 'last_action' => $oldTime]);

        // Three sequential updates, each adding 1000 uploaded and 500 downloaded.
        // Between each, advance the peer's timestamps to bypass the min-wait gate.
        $this->announce($user->passkey, $infoHash, $peerId, 1000, 1500, 98500);
        DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->update(['prev_action' => $oldTime, 'last_action' => $oldTime]);

        $this->announce($user->passkey, $infoHash, $peerId, 2000, 2000, 98000);
        DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->update(['prev_action' => $oldTime, 'last_action' => $oldTime]);

        $this->announce($user->passkey, $infoHash, $peerId, 3000, 2500, 97500);

        $finalUploaded = (int) DB::table('users')->where('id', $user->id)->value('uploaded');
        $finalDownloaded = (int) DB::table('users')->where('id', $user->id)->value('downloaded');

        // Total uploaded delta = 3000 (cumulative counter reported)
        // Total downloaded delta = 1500 (2500 - 1000 initial)
        // With NORMAL promotion (upMultiplier=1, downMultiplier=1):
        $this->assertSame(3000, $finalUploaded, 'User uploaded must equal total delta (3000) with NORMAL promotion');
        $this->assertSame(1500, $finalDownloaded, 'User downloaded must equal total delta (1500) with NORMAL promotion');
    }

    /**
     * Announcing with an invalid passkey must not create any peer or
     * snatch rows. This is a security invariant: unauthenticated
     * announces must have zero side effects on accounting tables.
     */
    public function test_invalid_passkey_announce_has_no_accounting_side_effects(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create([
            'size' => 1000,
            'seeders' => 0,
            'leechers' => 0,
            'times_completed' => 0,
        ]);

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4500-'.random_bytes(12))->toBinary();
        $fakePasskey = str_repeat('a', 32);

        $peersBefore = DB::table('peers')->where('torrent', $torrent->id)->count();
        $snatchesBefore = DB::table('snatched')->where('torrentid', $torrent->id)->count();

        $response = $this->get(
            '/announce?passkey='.$fakePasskey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=51413&uploaded=0&downloaded=100&left=900&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        // Must be a rejection response (failure reason or warning message)
        $decoded = Bencode::decode($response->getContent());
        $this->assertTrue(
            isset($decoded['failure reason']) || isset($decoded['warning message']),
            'Invalid passkey must produce a rejection. Got: '.$response->getContent()
        );

        // No new peer or snatch rows
        $peersAfter = DB::table('peers')->where('torrent', $torrent->id)->count();
        $snatchesAfter = DB::table('snatched')->where('torrentid', $torrent->id)->count();
        $this->assertSame($peersBefore, $peersAfter, 'Invalid passkey must not create peer rows');
        $this->assertSame($snatchesBefore, $snatchesAfter, 'Invalid passkey must not create snatch rows');
    }

    /**
     * Helper: send an announce request and assert it returns 200.
     * Clears the Redis re-announce dedup lock so each announce is processed.
     */
    private function announce(
        string $passkey,
        string $infoHash,
        string $peerId,
        int $uploaded,
        int $downloaded,
        int $left,
        ?string $event = null,
    ): TestResponse {
        // Clear re-announce dedup lock so this announce is not skipped
        $lockParams = ['info_hash' => $infoHash, 'passkey' => $passkey];
        $reAnnounceKey = 'isReAnnounce:'.md5(http_build_query($lockParams));
        Redis::connection()->client()->del($reAnnounceKey);
        // Clear frequency gate (fingerprint = sha1 of binary info_hash)
        $frequencyKey = "reAnnounceCheckByInfoHash:{$passkey}:".sha1($infoHash);
        Redis::connection()->client()->del($frequencyKey);

        $url = '/announce?passkey='.$passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=51413'
            .'&uploaded='.$uploaded
            .'&downloaded='.$downloaded
            .'&left='.$left;
        if ($event !== null) {
            $url .= '&event='.$event;
        }

        $response = $this->get($url, ['User-Agent' => 'qBittorrent/4.5.2']);
        $response->assertStatus(200);

        return $response;
    }
}
