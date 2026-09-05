<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique constraint on (uid, torrent_id) to torrent_buy_logs
     * to prevent duplicate purchases at the database level.
     *
     * Existing duplicate rows (if any) are collapsed by keeping only
     * the earliest purchase per (uid, torrent_id) pair.
     */
    public function up(): void
    {
        // Collapse duplicate rows, keeping the earliest by id.
        // MySQL doesn't allow deleting from a table referenced in a subquery,
        // so we wrap the subquery in a derived table.
        DB::statement(
            'DELETE FROM torrent_buy_logs WHERE id NOT IN ('
            .'SELECT min_id FROM (SELECT MIN(id) AS min_id FROM torrent_buy_logs GROUP BY uid, torrent_id) AS keep'
            .')'
        );

        Schema::table('torrent_buy_logs', function (Blueprint $table): void {
            $table->unique(['uid', 'torrent_id'], 'torrent_buy_logs_uid_torrent_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('torrent_buy_logs', function (Blueprint $table): void {
            $table->dropUnique('torrent_buy_logs_uid_torrent_id_unique');
        });
    }
};
