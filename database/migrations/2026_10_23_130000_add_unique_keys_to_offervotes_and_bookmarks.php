<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offer votes and bookmarks both ran check-then-insert outside any
     * transaction: two concurrent votes/toggles could pass the
     * existence check and double-insert — duplicated offer counters plus
     * a double offer-vote bonus, or duplicate bookmark rows. The unique
     * keys make the second insert lose at the engine level; existing
     * duplicates collapse to the earliest record first.
     */
    public function up(): void
    {
        DB::statement(
            'DELETE v2 FROM offervotes v1 JOIN offervotes v2 ON v2.offerid = v1.offerid AND v2.userid = v1.userid AND v2.id > v1.id'
        );
        Schema::table('offervotes', function (Blueprint $table) {
            $table->unique(['offerid', 'userid'], 'offervotes_offerid_userid_unique');
        });

        DB::statement(
            'DELETE b2 FROM bookmarks b1 JOIN bookmarks b2 ON b2.userid = b1.userid AND b2.torrentid = b1.torrentid AND b2.id > b1.id'
        );
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->unique(['userid', 'torrentid'], 'bookmarks_userid_torrentid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropUnique('bookmarks_userid_torrentid_unique');
        });
        Schema::table('offervotes', function (Blueprint $table) {
            $table->dropUnique('offervotes_offerid_userid_unique');
        });
    }
};
