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
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W2-04: Repeated event idempotency tests.
 *
 * Verifies that repeated started, completed, and stopped events do not
 * double-count accounting. This is a critical invariant for Octane/RoadRunner
 * where a client may re-send an event due to network retries.
 *
 * @group w2-04
 */
#[TestCategory(TestCategory::CONCURRENCY)]
final class AnnounceRepeatedEventTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Repeated "completed" events must not increment times_completed
     * more than once for the same peer/torrent pair.
     */
    public function test_repeated_completed_event_does_not_double_count(): void
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

        // First completed event — must increment times_completed by 1
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'completed');
        $timesCompletedAfter1 = (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed');
        $this->assertSame(1, $timesCompletedAfter1);

        // Second completed event (duplicate/retry) — must NOT increment again
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'completed');
        $timesCompletedAfter2 = (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed');
        $this->assertSame(1, $timesCompletedAfter2, 'Repeated completed event must not double-count times_completed');

        // Snatch record must still be finished=1, not 2
        $snatchFinished = (int) DB::table('snatched')
            ->where('torrentid', $torrent->id)
            ->where('userid', $user->id)
            ->value('finished');
        $this->assertSame(1, $snatchFinished, 'Snatch finished flag must remain 1 after repeated completed');
    }

    /**
     * Repeated "started" events must not create duplicate peer rows.
     */
    public function test_repeated_started_event_does_not_create_duplicate_peers(): void
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

        // First started event
        $this->announce($user->passkey, $infoHash, $peerId, 0, 0, 900, 'started');
        $peersAfter1 = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(1, $peersAfter1, 'First started must create exactly 1 peer row');

        // Second started event (duplicate/retry)
        $this->announce($user->passkey, $infoHash, $peerId, 0, 0, 900, 'started');
        $peersAfter2 = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('userid', $user->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(1, $peersAfter2, 'Repeated started must not create duplicate peer row');
    }

    /**
     * Repeated "stopped" events must not cause negative seeder/leecher counts.
     */
    public function test_repeated_stopped_event_does_not_cause_negative_counts(): void
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

        // Start and complete
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'completed');

        $seedersBefore = (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders');
        $this->assertSame(1, $seedersBefore);

        // First stopped event — must remove peer and decrement seeders
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'stopped');
        $seedersAfter1 = (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders');
        $this->assertSame(0, $seedersAfter1, 'First stopped must decrement seeders to 0');

        // Second stopped event (duplicate/retry) — must NOT go negative
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'stopped');
        $seedersAfter2 = (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders');
        $this->assertSame(0, $seedersAfter2, 'Repeated stopped must not cause negative seeders count');

        // No peer row should remain
        $peerCount = DB::table('peers')
            ->where('torrent', $torrent->id)
            ->where('peer_id', $peerId)
            ->count();
        $this->assertSame(0, $peerCount, 'No peer row should remain after stopped');
    }

    /**
     * Started → completed → stopped → started cycle must produce
     * consistent counts at each stage.
     */
    public function test_full_lifecycle_started_completed_stopped_started(): void
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

        // started (leeching)
        $this->announce($user->passkey, $infoHash, $peerId, 0, 0, 900, 'started');
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders'));
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers'));

        // completed (seeding)
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'completed');
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders'));
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers'));
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed'));

        // stopped (offline)
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 0, 'stopped');
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders'));
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers'));

        // started again (re-leeching)
        $this->announce($user->passkey, $infoHash, $peerId, 0, 100, 900, 'started');
        $this->assertSame(0, (int) DB::table('torrents')->where('id', $torrent->id)->value('seeders'));
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $torrent->id)->value('leechers'));

        // times_completed must still be 1, not 2
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $torrent->id)->value('times_completed'),
            'times_completed must not increment on re-start after stop');
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
        $lockParams = ['info_hash' => $infoHash, 'passkey' => $passkey];
        $reAnnounceKey = 'isReAnnounce:'.md5(http_build_query($lockParams));
        Redis::connection()->client()->del($reAnnounceKey);
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
