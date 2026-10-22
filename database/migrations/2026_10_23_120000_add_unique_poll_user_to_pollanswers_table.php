<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Poll voting checked hasVoted() before insert with no uniqueness
     * guarantee, so two concurrent votes from the same user could both
     * pass the check and double-insert — double poll counts and double
     * poll-vote bonus. The unique key makes the second insert lose at
     * the engine level; pre-existing duplicates collapse to the
     * earliest record first (same pattern as the magic table fix).
     */
    public function up(): void
    {
        DB::statement(
            'DELETE p2 FROM pollanswers p1 JOIN pollanswers p2 ON p2.pollid = p1.pollid AND p2.userid = p1.userid AND p2.id > p1.id'
        );

        Schema::table('pollanswers', function (Blueprint $table) {
            $table->unique(['pollid', 'userid'], 'pollanswers_pollid_userid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pollanswers', function (Blueprint $table) {
            $table->dropUnique('pollanswers_pollid_userid_unique');
        });
    }
};
