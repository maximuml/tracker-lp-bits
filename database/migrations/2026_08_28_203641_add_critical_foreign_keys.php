<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Add foreign keys for the most critical table relationships.
 *
 * Many FKs were skipped due to column type mismatches between child and parent
 * tables (legacy schema mixes mediumint, int, and bigint). The following
 * relationships could NOT be added because the child column type does not match
 * the parent PK type:
 *
 *  - All child columns referencing users.id (bigint unsigned):
 *      bookmarks.userid, snatched.userid, peers.userid, comments.user,
 *      thanks.userid, hit_and_runs.uid, torrent_buy_logs.uid,
 *      torrent_operation_logs.uid, torrent_secrets.uid, exam_users.uid,
 *      exam_progress.uid, user_medals.uid, user_passkeys.user_id,
 *      user_metas.uid, bonus_logs.uid, attendance.uid, friends.userid,
 *      friends.friendid, blocks.userid, blocks.blockid, shoutbox_reactions.user_id,
 *      posts.userid, topics.userid, readposts.userid, pollanswers.userid,
 *      offervotes.userid, iplog.userid, login_logs.uid, messages.sender,
 *      messages.receiver, news.userid, offers.userid
 *    (child columns are mediumint unsigned or int; parent is bigint unsigned)
 *
 *  - All child columns referencing torrents.id (mediumint unsigned) where child is int:
 *      hit_and_runs.torrent_id, torrent_tags.torrent_id, torrent_buy_logs.torrent_id,
 *      torrent_operation_logs.torrent_id, torrent_secrets.torrent_id,
 *      exam_progress.torrent_id
 *
 *  - torrent_tags.tag_id (int) → tags.id (bigint unsigned)
 *  - exam_users.exam_id (int) → exams.id (bigint unsigned)
 *  - exam_progress.exam_id (int) → exams.id (bigint unsigned)
 *  - exam_progress.exam_user_id (int) → exam_users.id (bigint unsigned)
 *  - user_medals.medal_id (int) → medals.id (bigint unsigned)
 *  - hit_and_runs.snatched_id (int) → snatched.id (bigint unsigned)
 *  - shoutbox_reactions.shoutbox_id (int unsigned) → shoutbox.id (int)
 *
 *  - SET NULL relationships (all reference users.id with type mismatch):
 *      messages.sender, messages.receiver, comments.editedby, posts.editedby,
 *      topics.userid
 *
 * Orphan check: all matching relationships had 0 orphan rows except
 * messages.sender (12 orphans) which was skipped due to type mismatch anyway.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // bookmarks.torrentid → torrents.id ON DELETE CASCADE
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->foreign('torrentid')->references('id')->on('torrents')->onDelete('cascade');
        });

        // snatched.torrentid → torrents.id ON DELETE CASCADE
        Schema::table('snatched', function (Blueprint $table) {
            $table->foreign('torrentid')->references('id')->on('torrents')->onDelete('cascade');
        });

        // peers.torrent → torrents.id ON DELETE CASCADE
        Schema::table('peers', function (Blueprint $table) {
            $table->foreign('torrent')->references('id')->on('torrents')->onDelete('cascade');
        });

        // thanks.torrentid → torrents.id ON DELETE CASCADE
        Schema::table('thanks', function (Blueprint $table) {
            $table->foreign('torrentid')->references('id')->on('torrents')->onDelete('cascade');
        });

        // files.torrent → torrents.id ON DELETE CASCADE
        Schema::table('files', function (Blueprint $table) {
            $table->foreign('torrent')->references('id')->on('torrents')->onDelete('cascade');
        });

        // posts.topicid → topics.id ON DELETE CASCADE
        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('topicid')->references('id')->on('topics')->onDelete('cascade');
        });

        // readposts.topicid → topics.id ON DELETE CASCADE
        Schema::table('readposts', function (Blueprint $table) {
            $table->foreign('topicid')->references('id')->on('topics')->onDelete('cascade');
        });

        // pollanswers.pollid → polls.id ON DELETE CASCADE
        Schema::table('pollanswers', function (Blueprint $table) {
            $table->foreign('pollid')->references('id')->on('polls')->onDelete('cascade');
        });

        // offervotes.offerid → offers.id ON DELETE CASCADE
        Schema::table('offervotes', function (Blueprint $table) {
            $table->foreign('offerid')->references('id')->on('offers')->onDelete('cascade');
        });

        // topics.forumid → forums.id ON DELETE CASCADE
        Schema::table('topics', function (Blueprint $table) {
            $table->foreign('forumid')->references('id')->on('forums')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['forumid']);
        });

        Schema::table('offervotes', function (Blueprint $table) {
            $table->dropForeign(['offerid']);
        });

        Schema::table('pollanswers', function (Blueprint $table) {
            $table->dropForeign(['pollid']);
        });

        Schema::table('readposts', function (Blueprint $table) {
            $table->dropForeign(['topicid']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['topicid']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['torrent']);
        });

        Schema::table('thanks', function (Blueprint $table) {
            $table->dropForeign(['torrentid']);
        });

        Schema::table('peers', function (Blueprint $table) {
            $table->dropForeign(['torrent']);
        });

        Schema::table('snatched', function (Blueprint $table) {
            $table->dropForeign(['torrentid']);
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropForeign(['torrentid']);
        });
    }
};
