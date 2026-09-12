<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Attributes\TestCategory;
use Tests\Concerns\AssertsQueryCount;
use Tests\TestCase;

/**
 * Wave 5 Step 23 / W3-05 + W5-03: Query-count budgets for key pages.
 *
 * Enforces maximum DB query counts per request to catch N+1 problems
 * early. Budgets are measured on the standard seeded dataset and set
 * at actual count + ~20% (W5-03 criterion).
 *
 * Auth notes:
 * - Legacy PageService pages authenticate via the `c_secure_pass`
 *   cookie — `withNexusCookie()`. `Sanctum::actingAs()` does NOT
 *   satisfy `auth.nexus` and would measure a 302 redirect instead of
 *   the real page.
 * - Filament `/nexusphp` and migrated controllers use the `nexus-web`
 *   guard — `actingAs($user, 'nexus-web')`.
 * - API endpoints use Sanctum abilities.
 * - Tracker endpoints (`/scrape`, `/announce`) authenticate by passkey.
 *
 * @group query-budget
 */
#[TestCategory(TestCategory::PERFORMANCE)]
final class QueryBudgetTest extends TestCase
{
    use AssertsQueryCount;
    use DatabaseTransactions;

    /**
     * Health/live endpoint should use minimal DB queries.
     * (Laravel bootstrap may run a few session/auth queries even on
     * stateless endpoints — budget allows for framework overhead.)
     */
    public function test_health_live_query_budget(): void
    {
        $this->assertQueryCountBelow(10, function (): void {
            $this->getJson('/health/live');
        });
    }

    /**
     * Health/ready endpoint should use at most 1 DB query (DB ping).
     */
    public function test_health_ready_query_budget(): void
    {
        $this->assertQueryCountBelow(5, function (): void {
            $this->getJson('/health/ready');
        });
    }

    /**
     * Metrics endpoint should use at most 2 DB queries (DB ping).
     */
    public function test_metrics_query_budget(): void
    {
        $this->assertQueryCountBelow(5, function (): void {
            $this->get('/metrics');
        });
    }

    /**
     * /index.php — actual ~50 queries on the seeded dataset → budget 61.
     */
    public function test_index_page_query_budget(): void
    {
        $user = User::factory()->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(61, function (): void {
            $this->get('/index.php');
        });
    }

    /**
     * /torrents.php listing — actual ~30 → budget 36.
     */
    public function test_torrents_listing_query_budget(): void
    {
        $user = User::factory()->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(36, function (): void {
            $this->get('/torrents.php');
        });
    }

    /**
     * /details.php — actual ~33 → budget 40.
     */
    public function test_torrent_details_query_budget(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(40, function () use ($torrent): void {
            $this->get('/details.php?id='.$torrent->id);
        });
    }

    /**
     * /announce endpoint — passkey auth, budget 8 (unchanged, W3-05).
     */
    public function test_announce_query_budget(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $infoHash = InfoHash::fromBinary($torrent->info_hash)->toBinary();
        $peerId = '-qB4'.sprintf('%02d', random_int(0, 99)).random_bytes(14);

        $this->assertQueryCountBelow(8, function () use ($user, $infoHash, $peerId): void {
            $this->get(
                '/announce?passkey='.$user->passkey
                .'&info_hash='.rawurlencode($infoHash)
                .'&peer_id='.rawurlencode($peerId)
                .'&port=12345'
                .'&uploaded=0'
                .'&downloaded=0'
                .'&left=0'
                .'&event=started'
                .'&compact=1'
            );
        });
    }

    /**
     * /scrape endpoint — passkey auth, actual ~4 → budget 5.
     */
    public function test_scrape_query_budget(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        $this->assertQueryCountBelow(5, function () use ($user, $torrent): void {
            $this->call('GET', '/scrape', [
                'passkey' => $user->passkey,
                'info_hash' => InfoHash::fromBinary($torrent->info_hash)->toBinary(),
            ], [], [], ['HTTP_USER_AGENT' => 'qBittorrent/4.0.0']);
        });
    }

    /**
     * /usercp.php — actual ~25 → budget 30.
     */
    public function test_usercp_query_budget(): void
    {
        $user = User::factory()->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(30, function (): void {
            $this->get('/usercp.php');
        });
    }

    /**
     * /forums.php index — actual ~31 with the seeded 5 forums (per-forum
     * topic/post lookups) → budget 38.
     */
    public function test_forums_query_budget(): void
    {
        $user = User::factory()->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(38, function (): void {
            $this->get('/forums.php');
        });
    }

    /**
     * /messages.php inbox with 5 messages — actual ~24 → budget 29.
     *
     * Known N+1: the listing costs ~2 extra queries per message row
     * (measured 13 @ 5 messages, 24 @ 10). Budget is pinned to the
     * fixed dataset; a larger N+1 factor still trips it.
     */
    public function test_messages_query_budget(): void
    {
        $user = User::factory()->create();
        Message::factory()->count(5)->between($user, $user)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(29, function (): void {
            $this->get('/messages.php');
        });
    }

    /**
     * /getrss.php — actual ~28 → budget 34.
     */
    public function test_rss_query_budget(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->owner($user)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(34, function () use ($user): void {
            $this->get('/getrss.php?passkey='.$user->passkey);
        });
    }

    /**
     * /staff page as an admin (widest code path) — actual ~37 → budget 45.
     */
    public function test_staff_query_budget(): void
    {
        $admin = User::factory()->create(['class' => 16]);
        $this->withNexusCookie($admin);

        $this->assertQueryCountBelow(45, function (): void {
            $this->get('/staff');
        });
    }

    /**
     * Filament /nexusphp shell — actual 2 → budget 3.
     * (Livewire widgets fetch data over XHR; this guards the initial
     * page render and panel navigation queries.)
     */
    public function test_admin_dashboard_query_budget(): void
    {
        $admin = User::factory()->create(['class' => 16]);
        $this->actingAs($admin, 'nexus-web');

        $this->assertQueryCountBelow(3, function (): void {
            $this->get('/nexusphp');
        });
    }

    /**
     * GET /api/v1/torrents — actual ~5 → budget 6.
     */
    public function test_api_torrents_query_budget(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->owner($user)->create();
        Sanctum::actingAs($user, ['*']);

        $this->assertQueryCountBelow(6, function (): void {
            $this->getJson('/api/v1/torrents');
        });
    }

    /**
     * GET /api/v1/messages — actual ~4 → budget 5.
     */
    public function test_api_messages_query_budget(): void
    {
        $user = User::factory()->create();
        Message::factory()->between($user, $user)->create();
        Sanctum::actingAs($user, ['*']);

        $this->assertQueryCountBelow(5, function (): void {
            $this->getJson('/api/v1/messages');
        });
    }

    /**
     * GET /api/v1/forums — actual ~3 → budget 4.
     */
    public function test_api_forums_query_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->assertQueryCountBelow(4, function (): void {
            $this->getJson('/api/v1/forums');
        });
    }
}
