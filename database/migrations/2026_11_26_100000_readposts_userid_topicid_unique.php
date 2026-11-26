<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * readposts gained duplicate (userid, topicid) rows when two concurrent
 * mark-as-read requests raced the check-then-insert in
 * TopicReadStateRepository::markPostRead. Collapse duplicates keeping the
 * furthest-read position, then enforce the natural key.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('readposts')
            ->select('userid', 'topicid', DB::raw('MAX(lastpostread) as furthest'), DB::raw('COUNT(*) as c'))
            ->groupBy('userid', 'topicid')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $dupe) {
            DB::table('readposts')
                ->where('userid', $dupe->userid)
                ->where('topicid', $dupe->topicid)
                ->delete();
            DB::table('readposts')->insert([
                'userid' => $dupe->userid,
                'topicid' => $dupe->topicid,
                'lastpostread' => $dupe->furthest,
            ]);
        }

        Schema::table('readposts', function (Blueprint $table): void {
            $table->unique(['userid', 'topicid'], 'readposts_userid_topicid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('readposts', function (Blueprint $table): void {
            $table->dropUnique('readposts_userid_topicid_unique');
        });
    }
};
