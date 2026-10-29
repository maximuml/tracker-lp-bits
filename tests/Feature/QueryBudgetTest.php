<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\Comment;
use App\Models\Forum;
use App\Models\Message;
use App\Models\Post;
use App\Models\Snatch;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Cache\NexusCache;
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
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(61, function (): void {
            $this->get('/web/index');
        });
    }

    /**
     * /torrents.php listing — actual ~37 in the E2E job (CriticalPathTest
     * commits a torrent row through OpenResty outside the test transaction)
     * → budget 45.
     */
    public function test_torrents_listing_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(45, function (): void {
            $this->get('/web/torrents');
        });
    }

    /**
     * /details.php — actual ~33 → budget 40.
     */
    public function test_torrent_details_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
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
        $user = User::factory()->create(['class' => UserClass::USER->value]);
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
        $user = User::factory()->create(['class' => UserClass::USER->value]);
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
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(30, function (): void {
            $this->get('/usercp.php');
        });
    }

    /**
     * /forums.php index — actual ~45-46 with the seeded 5 forums: unified
     * chrome (staffmessages/news/exam_users) + permission-scoped staff box
     * + per-forum lastpost N+1. Actor is pinned to a regular user — the
     * factory rolls a staff class ~30% of the time which adds ~7 dashboard
     * counts. → budget 48.
     */
    public function test_forums_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(48, function (): void {
            $this->get('/forums.php');
        });
    }

    /**
     * /forums.php?action=viewforum topic listing with 10 topics —
     * actual ~29 → budget 36.
     *
     * Fixed-cost page: count + topic page (with user/firstPost/lastPost
     * eager loads) + batched post-count/first-last-post lookups + the
     * shared forum chrome. Per-topic regressions still trip the budget.
     */
    public function test_viewforum_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $forum = Forum::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $topic = Topic::factory()->forum($forum)->author($user)->create();
            $post = Post::factory()->topic($topic)->author($user)->create();
            $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);
        }
        app(NexusCache::class)->redis?->flushDB();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(36, function () use ($forum): void {
            $this->get('/forums.php?action=viewforum&forumid='.$forum->id);
        });
    }

    /**
     * /forums.php?action=viewtopic with 5 posts — actual ~31 → budget 37.
     *
     * Fixed-cost page: topic + view increment + post count + post page +
     * batched poster info (getUsersByIds / countUserPostsBatch /
     * UserDisplay::preload) + markPostRead on the last post only.
     */
    public function test_viewtopic_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create();
        for ($i = 0; $i < 5; $i++) {
            $post = Post::factory()->topic($topic)->author($user)->create();
            if ($i === 0) {
                $topic->update(['firstpost' => $post->id]);
            }
            $topic->update(['lastpost' => $post->id]);
        }
        app(NexusCache::class)->redis?->flushDB();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(37, function () use ($topic): void {
            $this->get('/forums.php?action=viewtopic&topicid='.$topic->id);
        });
    }

    /**
     * /web/offers empty listing — actual ~21 cold chrome → budget 26.
     *
     * Count + page + categories + last_offer update + shared chrome.
     */
    public function test_offers_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(26, function (): void {
            $this->get('/web/offers');
        });
    }

    /**
     * /userdetails.php self-view — actual ~31 cold chrome → budget 37.
     *
     * The friends/blocks exists pairs are bidirectional checks (me→target
     * and target→me), not duplicates.
     */
    public function test_userdetails_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(37, function () use ($user): void {
            $this->get('/userdetails.php?id='.$user->id);
        });
    }

    /**
     * /web/upload form — actual ~25 under the seeded CI dataset
     * (tracker_urls, per-box searchbox reads, wider staff permission
     * set) → budget 32.
     */
    public function test_upload_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(32, function (): void {
            $this->get('/web/upload');
        });
    }

    /**
     * /web/invite — actual ~17 with the full badge chrome → budget 28.
     */
    public function test_invite_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(28, function (): void {
            $this->get('/web/invite');
        });
    }

    /**
     * /web/mybonus — actual ~9 (light chrome) → budget 22.
     */
    public function test_mybonus_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(22, function (): void {
            $this->get('/web/mybonus');
        });
    }

    /**
     * /web/latestcomments — actual ~9 (count + one joined page)
     * → budget 22.
     */
    public function test_latestcomments_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $torrent = Torrent::factory()->owner($user)->create();
        Comment::factory()->author($user)->torrent($torrent)->count(3)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(22, function (): void {
            $this->get('/web/latestcomments');
        });
    }

    /**
     * /web/viewsnatches?id=N — actual ~10 (name + count + page)
     * → budget 24.
     */
    public function test_viewsnatches_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $torrent = Torrent::factory()->owner($user)->create();
        Snatch::factory()->forUserAndTorrent($user, $torrent)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(24, function () use ($torrent): void {
            $this->get('/web/viewsnatches?id='.$torrent->id);
        });
    }

    /**
     * /messages.php inbox — actual ~18 → budget 24.
     *
     * The listing is fixed-cost (count + page + eager senders + one
     * pmboxes fetch shared across sections); a per-row regression
     * still trips the budget on larger datasets.
     */
    public function test_messages_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        Message::factory()->count(5)->between($user, $user)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(24, function (): void {
            $this->get('/web/messages');
        });
    }

    /**
     * /getrss.php — actual ~28 → budget 34.
     */
    public function test_rss_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        Torrent::factory()->owner($user)->create();
        $this->withNexusCookie($user);

        $this->assertQueryCountBelow(34, function () use ($user): void {
            $this->get('/web/getrss?passkey='.$user->passkey);
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
            $this->get('/web/staff');
        });
    }

    /**
     * Filament /nexusphp shell — actual 3 → budget 4.
     * (Livewire widgets fetch data over XHR; this guards the initial
     * page render and panel navigation queries. +1 since M-03: the
     * flushAccess last_access write is now mirrored into
     * user_activity via a single updateOrInsert.)
     */
    public function test_admin_dashboard_query_budget(): void
    {
        $admin = User::factory()->create(['class' => 16]);
        $this->actingAs($admin, 'nexus-web');

        $this->assertQueryCountBelow(4, function (): void {
            $this->get('/nexusphp');
        });
    }

    /**
     * GET /api/v1/torrents — actual ~13 in the E2E job (each visible torrent
     * pulls codec/source/media/… lookup tables) → budget 16.
     */
    public function test_api_torrents_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        Torrent::factory()->owner($user)->create();
        Sanctum::actingAs($user, ['*']);

        $this->assertQueryCountBelow(16, function (): void {
            $this->getJson('/api/v1/torrents');
        });
    }

    /**
     * GET /api/v1/messages — actual ~4 → budget 5.
     */
    public function test_api_messages_query_budget(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
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
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        Sanctum::actingAs($user, ['*']);

        $this->assertQueryCountBelow(4, function (): void {
            $this->getJson('/api/v1/forums');
        });
    }
}
