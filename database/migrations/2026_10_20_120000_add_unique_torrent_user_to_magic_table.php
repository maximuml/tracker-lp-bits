<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MagicRewardService used to check hasMagicRecord() outside any
     * transaction, so two concurrent gives could double-insert into
     * `magic` and double-transfer the bonus. The unique key makes the
     * second insert fail at the engine level; pre-existing duplicate
     * rows are collapsed to the earliest record first.
     */
    public function up(): void
    {
        DB::statement(
            'DELETE m2 FROM magic m1 JOIN magic m2 ON m2.torrentid = m1.torrentid AND m2.userid = m1.userid AND m2.id > m1.id'
        );

        Schema::table('magic', function (Blueprint $table) {
            $table->unique(['torrentid', 'userid'], 'magic_torrentid_userid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic', function (Blueprint $table) {
            $table->dropUnique('magic_torrentid_userid_unique');
        });
    }
};
