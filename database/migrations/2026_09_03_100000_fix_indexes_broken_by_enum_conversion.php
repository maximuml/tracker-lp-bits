<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix composite indexes that lost a column during the enum-to-boolean
 * conversion migration (2026_08_31_040000_convert_enum_yes_no_to_boolean).
 *
 * When MySQL drops a column that participates in a composite index, it
 * silently removes that column from the index rather than dropping the
 * whole index. The enum conversion migration dropped the original enum
 * column and renamed a temp column in its place, which caused the
 * following indexes to lose their middle column:
 *
 *   - peers_seeder_last_action_index: (seeder, last_action) → (last_action)
 *   - users_donor_donoruntil_index:   (donor, donoruntil)  → (donoruntil)
 *   - messages_receiver_unread_added_index: (receiver, unread, added) → (receiver, added)
 *   - snatched_torrentid_finished_completedat_index: (torrentid, finished, completedat) → (torrentid, completedat)
 *
 * This migration drops and recreates each index with the correct columns.
 */
return new class extends Migration
{
    /**
     * Indexes to fix: [table, index_name, [columns]].
     *
     * @var array<int, array{0: string, 1: string, 2: list<string>}>
     */
    private const INDEXES = [
        ['peers', 'peers_seeder_last_action_index', ['seeder', 'last_action']],
        ['users', 'users_donor_donoruntil_index', ['donor', 'donoruntil']],
        ['messages', 'messages_receiver_unread_added_index', ['receiver', 'unread', 'added']],
        ['snatched', 'snatched_torrentid_finished_completedat_index', ['torrentid', 'finished', 'completedat']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as [$table, $indexName, $columns]) {
            // Drop the broken index if it exists
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?
                 LIMIT 1',
                [$table, $indexName],
            );

            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }

            // Recreate with correct columns
            $columnList = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnList})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The down migration restores the broken state (indexes without
        // the middle column), which is not useful. We simply drop the
        // indexes since the original migration will recreate them on
        // a fresh install.
        foreach (self::INDEXES as [$table, $indexName]) {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?
                 LIMIT 1',
                [$table, $indexName],
            );

            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        }
    }
};
