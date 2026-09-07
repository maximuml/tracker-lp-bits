<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use App\ValueObjects\InfoHash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsQueryCount;
use Tests\TestCase;

/**
 * Wave 5 Step 23 / W3-05: Query-count budgets for key pages.
 *
 * Enforces maximum DB query counts per request to catch N+1 problems
 * early. Budgets are set generously above current counts to avoid
 * flaky tests, but low enough to catch regressions.
 *
 * W3-05 budgets (from audit plan):
 *   /index      ≤ 25
 *   /torrents   ≤ 15
 *   /details/{id} ≤ 20
 *   /announce   ≤ 8
 *   /usercp     ≤ 15
 *
 * @group query-budget
 */
final class QueryBudgetTest extends TestCase
{
    use AssertsQueryCount;

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
     * /index page should use at most 25 DB queries.
     *
     * W3-05 budget: ≤ 25
     */
    public function test_index_page_query_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['torrent:view']);

        $this->assertQueryCountBelow(25, function (): void {
            $this->get('/index');
        });
    }

    /**
     * /torrents listing should use at most 15 DB queries.
     *
     * W3-05 budget: ≤ 15
     */
    public function test_torrents_listing_query_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['torrent:list']);

        $this->assertQueryCountBelow(15, function (): void {
            $this->get('/torrents');
        });
    }

    /**
     * /details/{id} page should use at most 20 DB queries.
     *
     * W3-05 budget: ≤ 20
     */
    public function test_torrent_details_query_budget(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        Sanctum::actingAs($user, ['torrent:view']);

        $this->assertQueryCountBelow(20, function () use ($torrent): void {
            $this->get('/details/'.$torrent->id);
        });
    }

    /**
     * /announce endpoint should use at most 8 DB queries.
     *
     * W3-05 budget: ≤ 8
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
     * /usercp page should use at most 15 DB queries.
     *
     * W3-05 budget: ≤ 15
     */
    public function test_usercp_query_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['usercp:settings']);

        $this->assertQueryCountBelow(15, function (): void {
            $this->get('/usercp');
        });
    }
}
