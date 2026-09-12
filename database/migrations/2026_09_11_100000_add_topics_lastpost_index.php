<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5-02: Index topics.lastpost for the "unread posts" forum view.
 *
 * TopicRepository::getUnreadTopics() filters and orders by `lastpost`
 * alone (`WHERE lastpost > ? ORDER BY lastpost DESC`). The existing
 * composite indexes lead with `forumid`, so MySQL cannot use them for
 * a lastpost-only range — EXPLAIN showed type=ALL + filesort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->index('lastpost', 'topics_lastpost_index');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->dropIndex('topics_lastpost_index');
        });
    }
};
