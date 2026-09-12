<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\AnnounceRequestDto;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentListingRepository;
use App\Services\Announce\PeerLifecycle;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W5-02: EXPLAIN regression tests for the hot read paths.
 *
 * The announce loop and the torrent listing are the hottest queries in
 * the tracker: peers/users are hit on every client announce (every
 * 30–90 s per peer) and the listing on every browse. A plan that
 * degenerates to type=ALL is a production incident, so this test
 * captures the SQL the application actually executes and asserts the
 * optimizer picks an index.
 *
 * For each captured statement we run EXPLAIN with the real bindings and
 * assert that the examined table does not do a full scan (`type` = ALL)
 * and uses a non-NULL key. `type` = index is accepted only for the
 * bounded "latest torrents" listing where ORDER BY id DESC + LIMIT
 * walks the PK.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ExplainPlanRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Run $fn while recording every executed statement.
     *
     * @return list<QueryExecuted>
     */
    private function captureQueries(callable $fn): array
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        $fn();

        return $queries;
    }

    /** @return list<object> EXPLAIN rows for one executed statement. */
    private function explain(QueryExecuted $query): array
    {
        // MySQL 8.4+/9 default EXPLAIN emits the tree format; TRADITIONAL
        // keeps the tabular output these assertions inspect.
        return DB::select('EXPLAIN FORMAT=TRADITIONAL '.$query->sql, $query->bindings);
    }

    /**
     * Assert the EXPLAIN plan for $table does not degenerate to a full
     * table scan and uses a real index.
     *
     * @param  list<object>  $plan
     */
    private function assertTableUsesIndex(array $plan, string $table, string $context): void
    {
        $row = null;
        foreach ($plan as $candidate) {
            if (($candidate->table ?? null) === $table) {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row, "{$context}: EXPLAIN returned no row for table '{$table}'.");
        $this->assertNotSame(
            'ALL',
            $row->type,
            "{$context}: full table scan on '{$table}' (possible_keys: ".($row->possible_keys ?? 'none').').',
        );
        $this->assertNotNull(
            $row->key,
            "{$context}: no index chosen for '{$table}' (possible_keys: ".($row->possible_keys ?? 'none').').',
        );
    }

    private function listingRepository(): TorrentListingRepository
    {
        return app(TorrentListingRepository::class);
    }

    // --- torrent listing -------------------------------------------------

    public function test_torrent_listing_default_order_uses_index(): void
    {
        $queries = $this->captureQueries(fn () => $this->listingRepository()->getList([
            'where' => '',
            'where_bindings' => [],
            'fields' => ['torrents.id', 'torrents.name'],
            'search_box_id' => 0,
            'offset' => 0,
            'limit' => 50,
            'order_by' => [['torrents.id', 'desc']],
        ]));

        $this->assertNotEmpty($queries, 'getList executed no statements.');

        $plan = $this->explain($queries[0]);
        $this->assertTableUsesIndex($plan, 'torrents', 'default torrent listing');
    }

    public function test_torrent_listing_category_filter_uses_index(): void
    {
        $queries = $this->captureQueries(fn () => $this->listingRepository()->getList([
            'where' => 'torrents.category = ?',
            'where_bindings' => [1],
            'fields' => ['torrents.id'],
            'search_box_id' => 0,
            'offset' => 0,
            'limit' => 50,
            'order_by' => [['torrents.id', 'desc']],
        ]));

        $plan = $this->explain($queries[0]);
        $this->assertTableUsesIndex($plan, 'torrents', 'category-filtered listing');
    }

    public function test_torrent_listing_owner_filter_uses_index(): void
    {
        $queries = $this->captureQueries(fn () => $this->listingRepository()->getList([
            'where' => 'torrents.owner = ?',
            'where_bindings' => [1],
            'fields' => ['torrents.id'],
            'search_box_id' => 0,
            'offset' => 0,
            'limit' => 50,
            'order_by' => [],
        ]));

        $plan = $this->explain($queries[0]);
        $this->assertTableUsesIndex($plan, 'torrents', 'owner-filtered listing');
    }

    // --- announce path ----------------------------------------------------

    public function test_announce_torrent_lookup_by_info_hash_uses_unique_index(): void
    {
        // Seed one torrent row — on an empty table the optimizer answers
        // "no matching row in const table" instead of a real plan.
        $torrent = Torrent::factory()->create();
        $infoHash = (string) DB::table('torrents')->where('id', $torrent->id)->value('info_hash');

        // Same statement as AnnounceService::loadTorrent() — kept in
        // sync with app/Services/AnnounceService.php.
        $queries = $this->captureQueries(function () use ($infoHash): void {
            DB::table('torrents')
                ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                ->select([
                    'torrents.id', 'torrents.size', 'torrents.owner', 'torrents.sp_state',
                    'torrents.seeders', 'torrents.leechers', 'torrents.times_completed',
                    'torrents.banned', 'torrents.hr', 'torrents.approval_status', 'torrents.price',
                    'torrents.visible', 'torrents.last_action', 'categories.mode',
                ])
                ->where('torrents.info_hash', $infoHash)
                ->first();
        });

        $this->assertNotEmpty($queries);
        $plan = $this->explain($queries[0]);
        $this->assertTableUsesIndex($plan, 'torrents', 'announce info_hash lookup');

        $torrentsRow = null;
        foreach ($plan as $row) {
            if (($row->table ?? null) === 'torrents') {
                $torrentsRow = $row;
            }
        }
        $this->assertContains(
            $torrentsRow->type,
            ['const', 'eq_ref', 'ref'],
            'announce info_hash lookup should be a single-row unique read.',
        );
    }

    public function test_announce_peer_lookup_uses_composite_index(): void
    {
        $dto = new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary(random_bytes(20)),
            peerId: PeerId::fromBinary('-qB0000-'.random_bytes(12)),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: 'started',
            numWant: 50,
            compact: false,
            ipv4: '127.0.0.1',
            ipv6: null,
            ip: '127.0.0.1',
            userAgent: 'qBittorrent/4.0.0',
        );

        // Seed one peer row so the optimizer produces a real plan
        // instead of "no matching row in const table". peers.torrent /
        // peers.userid are FKs, so real parent rows are required.
        $torrent = Torrent::factory()->create();
        $user = User::factory()->create();
        DB::table('peers')->insert([
            'torrent' => $torrent->id,
            'userid' => $user->id,
            'peer_id' => $dto->peerId->toBinary(),
            'ip' => '127.0.0.1',
        ]);

        $lifecycle = new PeerLifecycle(
            $dto,
            ['id' => $torrent->id],
            ['id' => $user->id],
            date('Y-m-d H:i:s'),
        );

        $queries = $this->captureQueries(fn () => $lifecycle->findSelf());

        $this->assertNotEmpty($queries, 'findSelf executed no statements.');
        $plan = $this->explain($queries[0]);
        $this->assertTableUsesIndex($plan, 'peers', 'announce self peer lookup');

        $peersRow = null;
        foreach ($plan as $row) {
            if (($row->table ?? null) === 'peers') {
                $peersRow = $row;
            }
        }
        $this->assertContains(
            $peersRow->type,
            ['const', 'eq_ref', 'ref'],
            'announce self peer lookup should be a unique-index read.',
        );
    }
}
