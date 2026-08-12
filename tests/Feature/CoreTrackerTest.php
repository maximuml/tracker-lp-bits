<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Sanctum\Sanctum;
use Rhilip\Bencode\Bencode;
use Tests\TestCase;

class CoreTrackerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_api_detail_returns_torrent_json(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        Sanctum::actingAs($user, ['torrent:view']);

        $this->getJson('/api/v1/detail/' . $torrent->id)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.name', $torrent->name);
    }

    public function test_api_torrents_listing_returns_results(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->owner($user)->create();

        Sanctum::actingAs($user, ['torrent:list']);

        $this->getJson('/api/v1/torrents')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', static fn ($id) => $id > 0);
    }

    public function test_api_comments_list_returns_comments_for_torrent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        $comment = Comment::factory()->author($user)->torrent($torrent)->create();

        Sanctum::actingAs($user, ['torrent:view']);

        $this->getJson('/api/v1/comments?parent_id=' . $torrent->id . '&type=torrent')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.0.id', $comment->id);
    }

    public function test_web_torrents_listing_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->owner($user)->create();

        $this->withNexusCookie($user)
            ->get('/torrents')
            ->assertStatus(200)
            ->assertSee('Torrents');
    }

    public function test_web_details_page_renders_torrent(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $this->withNexusCookie($user)
            ->get('/details/' . $torrent->id)
            ->assertStatus(200)
            ->assertSee($torrent->name);
    }

    public function test_web_comment_post_creates_comment_and_redirects(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();

        $response = $this->withNexusCookie($commenter)
            ->post('/comment', [
                'type' => 'torrent',
                'pid' => (string) $torrent->id,
                'body' => 'Great torrent, thanks!',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('details', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('comments', [
            'torrent' => $torrent->id,
            'user' => $commenter->id,
            'text' => 'Great torrent, thanks!',
        ]);
    }

    public function test_scrape_returns_bencoded_dictionary_for_info_hash(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $this->assertSame(20, strlen($infoHash));

        $response = $this->get(
            '/scrape?passkey=' . $user->passkey
            . '&info_hash=' . rawurlencode($infoHash),
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('files', $decoded);
        $this->assertArrayNotHasKey('failure reason', $decoded);
    }

    public function test_announce_started_returns_bencoded_peer_list(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = '-qB4' . sprintf('%02d', random_int(0, 99)) . random_bytes(14);
        $peerId = PeerId::fromBinary($peerId)->toBinary();

        $response = $this->get(
            '/announce?passkey=' . $user->passkey
            . '&info_hash=' . rawurlencode($infoHash)
            . '&peer_id=' . rawurlencode($peerId)
            . '&port=12345'
            . '&uploaded=0'
            . '&downloaded=0'
            . '&left=0'
            . '&event=started',
            ['User-Agent' => 'qBittorrent/4.5.2']
        );

        $response->assertStatus(200);
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayNotHasKey('failure reason', $decoded);
        $this->assertArrayHasKey('interval', $decoded);
    }
}
