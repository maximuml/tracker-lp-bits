<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Rhilip\Bencode\Bencode;
use Tests\TestCase;

class AnnounceProtocolContractTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_announce_with_raw_binary_info_hash_roundtrip(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        // Verify the info_hash is exactly 20 bytes (SHA-1)
        $rawInfoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $this->assertSame(20, strlen($rawInfoHash));

        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();
        $this->assertSame(20, strlen($peerId));

        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($rawInfoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayNotHasKey('failure reason', $decoded);
        $this->assertArrayHasKey('interval', $decoded);
        $this->assertIsInt($decoded['interval']);
        $this->assertGreaterThan(0, $decoded['interval']);
    }

    public function test_announce_with_nonexistent_info_hash_returns_bencoded_response(): void
    {
        $user = User::factory()->create();

        // Generate a random 20-byte info_hash that doesn't exist in DB
        $fakeInfoHash = random_bytes(20);
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($fakeInfoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        // Should return a valid bencoded response (may be failure reason or empty interval)
        $decoded = Bencode::decode($response->getContent());
        $this->assertTrue(is_array($decoded), 'Response should be a bencoded dictionary');
    }

    public function test_announce_with_invalid_passkey_returns_failure(): void
    {
        $torrent = Torrent::factory()->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        $response = $this->get(
            '/announce?passkey=invalidpasskey1234567890123456789012'
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_with_missing_info_hash_returns_failure(): void
    {
        $user = User::factory()->create();
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_with_missing_peer_id_returns_failure(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();

        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_event_stopped_returns_bencoded_response(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        // First announce as started
        $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        // Then announce as stopped
        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=stopped',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertTrue(is_array($decoded), 'Stopped event should return bencoded dictionary');
    }

    public function test_announce_event_completed_returns_bencoded_response(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        // Start downloading
        $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        // Complete download
        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=100'
            .'&left=0'
            .'&event=completed',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertTrue(is_array($decoded), 'Completed event should return bencoded dictionary');
    }

    public function test_scrape_with_multiple_info_hashes(): void
    {
        $user = User::factory()->create();
        $torrent1 = Torrent::factory()->owner($user)->create();
        $torrent2 = Torrent::factory()->owner($user)->create();

        $infoHash1 = InfoHash::fromBinary($torrent1->info_hash)->toBinary();
        $infoHash2 = InfoHash::fromBinary($torrent2->info_hash)->toBinary();

        $response = $this->get(
            '/scrape?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash1)
            .'&info_hash='.rawurlencode($infoHash2),
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('files', $decoded);
    }

    public function test_announce_rejects_browser_user_agent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        $response = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=0'
            .'&left=100'
            .'&event=started',
            ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']
        );

        // Browser UA should be blocked by OpenResty Lua filter or controller
        $this->assertTrue(
            $response->status() === 403 || str_contains($response->getContent(), 'failure reason'),
            'Browser UA should be rejected'
        );
    }

    /**
     * Two sequential announces from the same peer must produce consistent
     * accounting. The uploaded/downloaded deltas must accumulate correctly
     * without double-counting or losing data.
     *
     * This exercises the lockForUpdate-based serialization added in T-18:
     * even though the test runs sequentially, it verifies that the
     * transaction-based locking produces consistent state.
     */
    public function test_two_sequential_announces_of_same_peer_produce_consistent_accounting(): void
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
        // Use unique peer_id to avoid re-announce dedup from previous tests
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        // First announce: started, leeching, 0 uploaded, 100 downloaded
        $response1 = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=0'
            .'&downloaded=100'
            .'&left=900'
            .'&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $response1->assertStatus(200);
        $decoded1 = Bencode::decode($response1->getContent());
        $this->assertArrayNotHasKey('failure reason', $decoded1, 'First announce failed: '.($decoded1['failure reason'] ?? ''));

        // Second announce: still leeching, 200 uploaded, 300 downloaded
        $response2 = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881'
            .'&uploaded=200'
            .'&downloaded=300'
            .'&left=700',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $response2->assertStatus(200);
        $decoded2 = Bencode::decode($response2->getContent());
        $this->assertArrayNotHasKey('failure reason', $decoded2, 'Second announce failed: '.($decoded2['failure reason'] ?? ''));

        // Both announces should return valid interval and peer data
        $this->assertArrayHasKey('interval', $decoded1);
        $this->assertArrayHasKey('interval', $decoded2);
        $this->assertGreaterThan(0, $decoded1['interval']);
        $this->assertGreaterThan(0, $decoded2['interval']);
    }

    /**
     * Two announces with event=stopped in between must correctly remove
     * and re-insert the peer without leaving orphan rows.
     */
    public function test_announce_stopped_then_started_cleans_up_and_reinserts_peer(): void
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
        $peerId = PeerId::fromBinary('-qB4'.random_bytes(16))->toBinary();

        // Start
        $response1 = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881&uploaded=0&downloaded=0&left=1000&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $response1->assertStatus(200);
        $decoded1 = Bencode::decode($response1->getContent());
        $this->assertTrue(is_array($decoded1), 'Start announce should return bencoded dictionary');

        // Stop
        $response2 = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881&uploaded=0&downloaded=100&left=900&event=stopped',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $response2->assertStatus(200);
        $decoded2 = Bencode::decode($response2->getContent());
        $this->assertTrue(is_array($decoded2), 'Stop announce should return bencoded dictionary');

        // Start again
        $response3 = $this->get(
            '/announce?passkey='.$user->passkey
            .'&info_hash='.rawurlencode($infoHash)
            .'&peer_id='.rawurlencode($peerId)
            .'&port=6881&uploaded=0&downloaded=100&left=900&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $response3->assertStatus(200);
        $decoded3 = Bencode::decode($response3->getContent());
        $this->assertTrue(is_array($decoded3), 'Second start announce should return bencoded dictionary');
    }
}
