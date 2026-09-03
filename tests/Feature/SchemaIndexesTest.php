<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies that database indexes match the expected schema.
 *
 * Catches "schema drift" — indexes that were silently corrupted by
 * migrations that drop columns (e.g. enum-to-boolean conversion removes
 * a column from a composite index without dropping the index itself).
 *
 * The expected indexes are keyed by table name and index name, with the
 * expected column list in ordinal order.
 */
final class SchemaIndexesTest extends TestCase
{
    /**
     * Expected indexes for key tables.
     *
     * Format: [table => [index_name => [col1, col2, ...]]]
     *
     * @var array<string, array<string, list<string>>>
     */
    private const EXPECTED_INDEXES = [
        'peers' => [
            'PRIMARY' => ['id'],
            'peers_seeder_last_action_index' => ['seeder', 'last_action'],
            'peers_last_action_index' => ['last_action'],
            'peers_peer_id_index' => ['peer_id'],
            'peers_userid_index' => ['userid'],
            'peers_torrent_peer_id_userid_unique' => ['torrent', 'peer_id', 'userid'],
        ],
        'snatched' => [
            'PRIMARY' => ['id'],
            'snatched_torrentid_userid_unique' => ['torrentid', 'userid'],
            'snatched_torrentid_finished_completedat_index' => ['torrentid', 'finished', 'completedat'],
            'snatched_userid_index' => ['userid'],
            'snatched_completedat_index' => ['completedat'],
            'snatched_buy_log_id_index' => ['buy_log_id'],
            'snatched_hit_and_run_id_index' => ['hit_and_run_id'],
        ],
        'torrents' => [
            'PRIMARY' => ['id'],
            'torrents_info_hash_unique' => ['info_hash'],
            'torrents_name_index' => ['name'],
            'torrents_owner_index' => ['owner'],
            'torrents_added_index' => ['added'],
            'torrents_last_action_index' => ['last_action'],
            'torrents_pieces_hash_index' => ['pieces_hash'],
            'torrents_url_index' => ['url'],
            'torrents_category_visible_banned_index' => ['category'],
            'torrents_visible_pos_state_id_index' => ['pos_state', 'id'],
            'torrents_visible_banned_pos_state_id_index' => ['pos_state', 'id'],
            'torrents_promotion_until_promotion_time_type_index' => ['promotion_until', 'promotion_time_type'],
        ],
        'users' => [
            'PRIMARY' => ['id'],
            'users_username_unique' => ['username'],
            'users_passkey_index' => ['passkey'],
            'users_class_index' => ['class'],
            'users_country_index' => ['country'],
            'users_ip_index' => ['ip'],
            'users_status_added_index' => ['status', 'added'],
            'users_last_access_index' => ['last_access'],
            'users_uploaded_index' => ['uploaded'],
            'users_downloaded_index' => ['downloaded'],
            'users_cheat_index' => ['cheat'],
            'users_tracker_url_id_index' => ['tracker_url_id'],
            'users_email_index' => ['email'],
            'users_donor_donoruntil_index' => ['donor', 'donoruntil'],
        ],
        'messages' => [
            'PRIMARY' => ['id'],
            'messages_receiver_index' => ['receiver'],
            'messages_sender_index' => ['sender'],
            'messages_receiver_unread_added_index' => ['receiver', 'unread', 'added'],
        ],
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function indexProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $cases["{$table}.{$indexName}"] = [$table, $indexName, $columns];
            }
        }

        return $cases;
    }

    /**
     * @param  string  $table  Table name.
     * @param  string  $indexName  Index name.
     * @param  list<string>  $expectedColumns  Expected column names in ordinal order.
     */
    #[DataProvider('indexProvider')]
    public function test_index_has_correct_columns(string $table, string $indexName, array $expectedColumns): void
    {
        $rows = DB::select(
            'SELECT column_name FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             ORDER BY seq_in_index',
            [$table, $indexName],
        );

        $this->assertNotEmpty(
            $rows,
            sprintf('Index "%s" on table "%s" does not exist.', $indexName, $table),
        );

        $actual = array_map(fn ($row) => array_values((array) $row)[0], $rows);

        $this->assertSame(
            $expectedColumns,
            $actual,
            sprintf(
                'Index "%s" on table "%s" has columns [%s] but expected [%s]. '
                .'This may indicate schema drift from a column-dropping migration.',
                $indexName,
                $table,
                implode(', ', $actual),
                implode(', ', $expectedColumns),
            ),
        );
    }

    /**
     * Test that no unexpected indexes exist on the key tables.
     * This catches stale indexes that should have been dropped.
     */
    public function test_no_unexpected_indexes_on_key_tables(): void
    {
        foreach (self::EXPECTED_INDEXES as $table => $expectedIndexes) {
            $rows = DB::select(
                'SELECT DISTINCT index_name FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                 ORDER BY index_name',
                [$table],
            );

            $actual = array_map(fn ($row) => array_values((array) $row)[0], $rows);
            $expected = array_keys($expectedIndexes);

            $unexpected = array_diff($actual, $expected);
            $missing = array_diff($expected, $actual);

            $this->assertEmpty(
                $unexpected,
                sprintf(
                    'Table "%s" has unexpected indexes: [%s]. Expected: [%s]',
                    $table,
                    implode(', ', $unexpected),
                    implode(', ', $expected),
                ),
            );

            $this->assertEmpty(
                $missing,
                sprintf(
                    'Table "%s" is missing expected indexes: [%s]',
                    $table,
                    implode(', ', $missing),
                ),
            );
        }
    }

    /**
     * Test that the composite indexes specifically broken by the
     * enum-to-boolean conversion migration are now correct.
     *
     * This is a regression test for the fix migration
     * 2026_09_03_100000_fix_indexes_broken_by_enum_conversion.
     */
    public function test_composite_indexes_have_all_columns_after_enum_conversion(): void
    {
        $compositeIndexes = [
            ['peers', 'peers_seeder_last_action_index', ['seeder', 'last_action']],
            ['users', 'users_donor_donoruntil_index', ['donor', 'donoruntil']],
            ['messages', 'messages_receiver_unread_added_index', ['receiver', 'unread', 'added']],
            ['snatched', 'snatched_torrentid_finished_completedat_index', ['torrentid', 'finished', 'completedat']],
        ];

        foreach ($compositeIndexes as [$table, $indexName, $expectedColumns]) {
            $rows = DB::select(
                'SELECT column_name FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?
                 ORDER BY seq_in_index',
                [$table, $indexName],
            );

            $this->assertNotEmpty($rows, "Index {$indexName} on {$table} is missing.");
            $this->assertCount(
                count($expectedColumns),
                $rows,
                sprintf(
                    'Index "%s" on "%s" has %d columns but expected %d. '
                    .'The enum-to-boolean conversion may have dropped a column from this composite index.',
                    $indexName,
                    $table,
                    count($rows),
                    count($expectedColumns),
                ),
            );
        }
    }
}
