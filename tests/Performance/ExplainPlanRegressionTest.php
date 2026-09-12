<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\DTOs\AnnounceRequestDto;
use App\Models\Message;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\TopicRepository;
use App\Repositories\ToptenRepository;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentListingRepository;
use App\Repositories\UserSearchRepository;
use App\Services\Announce\PeerLifecycle;
use App\Services\Cleanup\Tasks\PeerCleanupTask;
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
#[TestCategory(TestCategory::PERFORMANCE, TestCategory::SERVICE_INTEGRATION)]
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
     * Explain every captured statement and return the first plan that
     * contains a row for $table. Needed when the code under test runs
     * several statements (settings loads, eager loads, count queries).
     *
     * @param  list<QueryExecuted>  $queries
     * @return list<object>
     */
    private function explainFirstForTable(array $queries, string $table, string $context): array
    {
        foreach ($queries as $query) {
            $plan = $this->explain($query);
            foreach ($plan as $row) {
                if (($row->table ?? null) === $table) {
                    return $plan;
                }
            }
        }

        $this->fail("{$context}: no captured statement produced an EXPLAIN row for '{$table}'.");
    }

    /**
     * Assert the EXPLAIN plan for $table does not degenerate to a full
     * table scan and uses a real index.
     *
     * @param  list<object>  $plan
     */
    private function assertTableUsesIndex(array $plan, string $table, string $context): object
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

        return $row;
    }

    // --- torrent listing -------------------------------------------------

    public function test_torrent_listing_default_order_uses_index(): void
    {
        $queries = $this->captureQueries(fn () => app(TorrentListingRepository::class)->getList([
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
        $row = $this->assertTableUsesIndex($plan, 'torrents', 'default torrent listing');
        $this->assertStringNotContainsString(
            'filesort',
            (string) ($row->Extra ?? ''),
            'default torrent listing should walk the PK, not sort rows.',
        );
    }

    public function test_torrent_listing_category_filter_uses_index(): void
    {
        $queries = $this->captureQueries(fn () => app(TorrentListingRepository::class)->getList([
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
        $queries = $this->captureQueries(fn () => app(TorrentListingRepository::class)->getList([
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

    // --- details page -----------------------------------------------------

    public function test_torrent_details_lookup_uses_primary_key(): void
    {
        $torrent = Torrent::factory()->create();

        $queries = $this->captureQueries(
            fn () => app(TorrentDetailRepository::class)->getTorrent($torrent->id)
        );

        $plan = $this->explainFirstForTable($queries, 'torrents', 'torrent details');
        $row = $this->assertTableUsesIndex($plan, 'torrents', 'torrent details');
        $this->assertContains(
            $row->type,
            ['const', 'eq_ref', 'ref'],
            'details page should read the torrent row by primary key.',
        );
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
        $row = $this->assertTableUsesIndex($plan, 'torrents', 'announce info_hash lookup');
        $this->assertContains(
            $row->type,
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
        $row = $this->assertTableUsesIndex($plan, 'peers', 'announce self peer lookup');
        $this->assertContains(
            $row->type,
            ['const', 'eq_ref', 'ref'],
            'announce self peer lookup should be a unique-index read.',
        );
    }

    // --- topten -----------------------------------------------------------

    public function test_topten_user_sections_use_indexes(): void
    {
        User::factory()->create(['uploaded' => 107374182400, 'downloaded' => 1073741824]);

        $queries = $this->captureQueries(
            fn () => app(ToptenRepository::class)->page(1, 10, 'ul')
        );

        $checked = 0;
        $fullScans = 0;
        foreach ($queries as $query) {
            $plan = $this->explain($query);
            foreach ($plan as $row) {
                if (($row->table ?? null) !== 'users') {
                    continue;
                }
                // The "fastest downloaders" section orders by a computed
                // expression (downloaded / account age) over all enabled
                // users — no selective predicate exists, so a bounded
                // scan is inherent. Pin the exception: it must stay the
                // ONLY unindexed section and must stay LIMIT-bounded.
                if ($row->type === 'ALL') {
                    $fullScans++;
                    $this->assertMatchesRegularExpression(
                        '/limit\s+\d+/i',
                        $query->sql,
                        'topten full scan must stay LIMIT-bounded.',
                    );
                    $this->assertStringContainsString(
                        'downspeed',
                        $query->sql,
                        'only the computed-speed section may full-scan users.',
                    );

                    continue;
                }
                $this->assertNotNull(
                    $row->key,
                    'topten user section: no index chosen (possible_keys: '.($row->possible_keys ?? 'none').').',
                );
                $checked++;
            }
        }

        $this->assertSame(1, $fullScans, 'exactly one topten section (fastest downloaders) may full-scan users.');
        $this->assertGreaterThan(0, $checked, 'topten page executed no indexed statements touching users.');
    }

    // --- user search --------------------------------------------------------

    public function test_admin_user_search_by_username_uses_index(): void
    {
        // Deterministic username: UserSearchRepository::hasWildcard()
        // treats _ % ? * as wildcards, and faker names may contain them —
        // a wildcard would turn the exact-match into a LIKE range scan.
        $user = User::factory()->create(['username' => 'admsearch'.random_int(10000, 99999)]);

        $queries = $this->captureQueries(
            fn () => app(UserSearchRepository::class)->administrativeSearch(['n' => $user->username], false)
        );

        $plan = $this->explainFirstForTable($queries, 'u', 'admin user search');
        $row = $this->assertTableUsesIndex($plan, 'u', 'admin user search by username');
        $this->assertContains(
            $row->type,
            ['const', 'eq_ref', 'ref'],
            'exact username search should hit users_username_unique.',
        );
    }

    // --- unread forum / PM --------------------------------------------------

    public function test_unread_forum_topics_use_index(): void
    {
        Topic::factory()->create(['lastpost' => 1]);

        $queries = $this->captureQueries(
            fn () => app(TopicRepository::class)->getUnreadTopics(0, null, 100)
        );

        $plan = $this->explainFirstForTable($queries, 'topics', 'unread forum topics');
        $row = $this->assertTableUsesIndex($plan, 'topics', 'unread forum topics');
        $this->assertStringNotContainsString(
            'filesort',
            (string) ($row->Extra ?? ''),
            'unread topics should walk topics_lastpost_index, not sort rows.',
        );
    }

    public function test_unread_pm_notifications_use_index(): void
    {
        $user = User::factory()->create();
        Message::factory()->create(['receiver' => $user->id, 'unread' => true]);

        $queries = $this->captureQueries(
            fn () => app(MessageRepository::class)->getUnreadPmNotifications($user->id, 0, 10)
        );

        $plan = $this->explainFirstForTable($queries, 'messages', 'unread PM notifications');
        $this->assertTableUsesIndex($plan, 'messages', 'unread PM notifications');
    }

    public function test_inbox_listing_uses_receiver_index(): void
    {
        $user = User::factory()->create();
        Message::factory()->create(['receiver' => $user->id, 'location' => 1]);

        $queries = $this->captureQueries(
            fn () => app(MessageRepository::class)->getMailboxMessages($user->id, 1, '', 'both', null, 0, 30)
        );

        $plan = $this->explainFirstForTable($queries, 'messages', 'inbox listing');
        $this->assertTableUsesIndex($plan, 'messages', 'inbox listing');
    }

    // --- cleanup ------------------------------------------------------------

    public function test_cleanup_prune_peers_uses_index(): void
    {
        $torrent = Torrent::factory()->create();
        $user = User::factory()->create();
        DB::table('peers')->insert([
            'torrent' => $torrent->id,
            'userid' => $user->id,
            'peer_id' => random_bytes(20),
            'ip' => '127.0.0.1',
        ]);

        $queries = $this->captureQueries(
            fn () => app(PeerCleanupTask::class)->prunePeers()
        );

        $plan = $this->explainFirstForTable($queries, 'peers', 'cleanup prune peers');
        $this->assertTableUsesIndex($plan, 'peers', 'cleanup prune peers');
    }

    public function test_cleanup_seed_bonus_reset_does_not_full_scan(): void
    {
        $queries = $this->captureQueries(
            fn () => app(PeerCleanupTask::class)->resetSeedBonusCounters()
        );

        $plan = $this->explainFirstForTable($queries, 'users', 'cleanup seed bonus reset');
        $this->assertTableUsesIndex($plan, 'users', 'cleanup seed bonus reset');
    }
}
