<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Rhilip\Bencode\Bencode;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Byte-level wire format of announce/scrape responses (BEP 3/7/23/48).
 * Clients parse these exactly, so key sets and peer encodings are pinned.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AnnounceBencodeFormatTest extends TestCase
{
    use DatabaseTransactions;

    private const ANNOUNCE_KEYS = ['complete', 'downloaded', 'incomplete', 'interval', 'min interval', 'peers'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_compact_announce_encodes_ipv4_and_ipv6_peers_as_binary(): void
    {
        [$user, $torrent] = $this->userAndTorrent();
        $this->peer($torrent, ipv4: '203.0.113.7', port: 51414);
        $this->peer($torrent, ipv6: '2001:db8::1', port: 6882);

        $response = $this->announce($user, $torrent, ['compact' => 1]);
        $decoded = $this->decodeCanonical($response);

        $this->assertSame(
            [...self::ANNOUNCE_KEYS, 'peers6'],
            $this->sortedKeys($decoded)
        );
        $this->assertSame(inet_pton('203.0.113.7').pack('n', 51414), $decoded['peers']);
        $this->assertSame(inet_pton('2001:db8::1').pack('n', 6882), $decoded['peers6']);
        // Two seeded leechers plus the announcing leecher itself.
        $this->assertSame(3, $decoded['incomplete']);
        $this->assertSame(0, $decoded['complete']);
        foreach (['interval', 'min interval', 'complete', 'incomplete', 'downloaded'] as $key) {
            $this->assertIsInt($decoded[$key], $key);
        }
    }

    public function test_compact_announce_without_peers_returns_empty_strings(): void
    {
        [$user, $torrent] = $this->userAndTorrent();

        $decoded = $this->decodeCanonical($this->announce($user, $torrent, ['compact' => 1]));

        $this->assertSame('', $decoded['peers']);
        $this->assertSame('', $decoded['peers6']);
    }

    public function test_non_compact_announce_returns_peer_dictionaries(): void
    {
        [$user, $torrent] = $this->userAndTorrent();
        $peerId = $this->peer($torrent, ipv4: '198.51.100.9', port: 6889);

        $decoded = $this->decodeCanonical($this->announce($user, $torrent, ['compact' => 0]));

        $this->assertSame(self::ANNOUNCE_KEYS, $this->sortedKeys($decoded));
        $this->assertIsArray($decoded['peers']);
        $this->assertCount(1, $decoded['peers']);
        $peer = $decoded['peers'][0];
        $this->assertSame(['ip', 'peer id', 'port'], $this->sortedKeys($peer));
        $this->assertSame($peerId, $peer['peer id']);
        $this->assertSame(20, strlen($peer['peer id']));
        $this->assertSame('198.51.100.9', $peer['ip']);
        $this->assertSame(6889, $peer['port']);
    }

    public function test_failure_response_contains_only_failure_reason(): void
    {
        $torrent = Torrent::factory()->create();
        // A well-formed unknown passkey yields a *warning* dict; a malformed
        // one fails validation and must produce a bare failure dict.
        $user = User::factory()->make(['passkey' => 'short']);

        $decoded = $this->decodeCanonical($this->announce($user, $torrent));

        $this->assertSame(['failure reason'], array_keys($decoded));
        $this->assertIsString($decoded['failure reason']);
        $this->assertNotSame('', $decoded['failure reason']);
    }

    public function test_scrape_files_are_keyed_by_raw_info_hash(): void
    {
        [$user, $torrent] = $this->userAndTorrent();
        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();

        $response = $this->get(
            '/scrape?passkey='.$user->passkey.'&info_hash='.rawurlencode($infoHash),
            ['User-Agent' => 'qBittorrent/4.5.2']
        );
        $decoded = $this->decodeCanonical($response);

        $this->assertSame(['files'], array_keys($decoded));
        $this->assertSame([$infoHash], array_map('strval', array_keys($decoded['files'])));
        $stats = $decoded['files'][$infoHash];
        $this->assertSame(['complete', 'downloaded', 'incomplete'], $this->sortedKeys($stats));
        foreach ($stats as $key => $value) {
            $this->assertIsInt($value, (string) $key);
        }
    }

    /** @return array{User, Torrent} */
    private function userAndTorrent(): array
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        return [$user, $torrent];
    }

    private function peer(Torrent $torrent, string $ipv4 = '', string $ipv6 = '', int $port = 6881): string
    {
        $peerId = PeerId::fromBinary('-TR4000-'.random_bytes(12))->toBinary();
        Peer::factory()
            ->torrent($torrent)
            ->user(User::factory()->create())
            ->leecher()
            ->create([
                'peer_id' => $peerId,
                'ip' => $ipv4 !== '' ? $ipv4 : $ipv6,
                'ipv4' => $ipv4,
                'ipv6' => $ipv6,
                'port' => $port,
            ]);

        return $peerId;
    }

    /** @param array<string, int> $extra */
    private function announce(User $user, Torrent $torrent, array $extra = []): TestResponse
    {
        $query = http_build_query([
            'passkey' => $user->passkey,
            'info_hash' => InfoHash::fromBinary($torrent->info_hash)->toBinary(),
            'peer_id' => PeerId::fromBinary('-qB4520-'.random_bytes(12))->toBinary(),
            // 6881-6889 are on the default port blacklist (warning response).
            'port' => 51413,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
            'event' => 'started',
            ...$extra,
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->get('/announce?'.$query, ['User-Agent' => 'qBittorrent/4.5.2']);
    }

    /**
     * Decodes and asserts the body is canonical bencode (sorted keys,
     * nothing trailing) by checking it re-encodes byte-for-byte.
     *
     * @return array<array-key, mixed>
     */
    private function decodeCanonical(TestResponse $response): array
    {
        $response->assertStatus(200);
        $body = (string) $response->getContent();
        $decoded = Bencode::decode($body);
        $this->assertIsArray($decoded);
        $this->assertSame($body, Bencode::encode($decoded), 'Response is not canonical bencode');

        return $decoded;
    }

    /**
     * @param  array<array-key, mixed>  $dict
     * @return list<string>
     */
    private function sortedKeys(array $dict): array
    {
        $keys = array_map('strval', array_keys($dict));
        sort($keys, SORT_STRING);

        return $keys;
    }
}
