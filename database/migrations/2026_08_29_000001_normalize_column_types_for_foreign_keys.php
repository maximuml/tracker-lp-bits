<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Normalize column types so child columns match their parent PK type,
 * then add the remaining ~40 foreign keys that were skipped in Sprint 42
 * due to type mismatches (mediumint/int/bigint).
 *
 * Parent → child type mapping:
 *  - users.id (bigint unsigned)        → all uid/userid/sender/receiver columns
 *  - torrents.id (mediumint unsigned)  → torrent_id columns (int → mediumint)
 *  - tags.id (bigint unsigned)         → torrent_tags.tag_id (int → bigint)
 *  - exams.id (bigint unsigned)        → exam_id columns (int → bigint)
 *  - exam_users.id (bigint unsigned)   → exam_progress.exam_user_id (int → bigint)
 *  - medals.id (bigint unsigned)       → user_medals.medal_id (int → bigint)
 *  - snatched.id (bigint unsigned)     → hit_and_runs.snatched_id (int → bigint)
 *  - shoutbox.id (int)                 → shoutbox_reactions.shoutbox_id (int unsigned → int)
 *
 * Orphan handling:
 *  - comments.user=2 (user not found): set to 0 (anonymous/system)
 *  - exam_progress.torrent_id=-1 (sentinel): set to NULL, make column nullable
 *  - messages.sender=0 (system messages): make column nullable, set 0 → NULL
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────────
        // Step 1: Clean orphan rows
        // ──────────────────────────────────────────────────────────────

        // comments.user=2 → user does not exist, set to 0 (anonymous)
        DB::table('comments')
            ->whereNotIn('user', function ($q) {
                $q->select('id')->from('users');
            })
            ->where('user', '>', 0)
            ->update(['user' => 0]);

        // exam_progress.torrent_id=-1 → sentinel value, set to NULL
        DB::table('exam_progress')
            ->where('torrent_id', -1)
            ->update(['torrent_id' => null]);

        // messages.sender=0 → system messages, set to NULL
        DB::table('messages')
            ->where('sender', 0)
            ->update(['sender' => null]);

        // ──────────────────────────────────────────────────────────────
        // Step 2: Normalize column types to match parent PK
        // ──────────────────────────────────────────────────────────────

        // users.id = bigint unsigned → all child columns must be bigint unsigned

        // mediumint unsigned → bigint unsigned
        $mediumToBigint = [
            'bookmarks' => ['userid'],
            'snatched' => ['userid'],
            'peers' => ['userid'],
            'comments' => ['user'],
            'thanks' => ['userid'],
            'friends' => ['userid', 'friendid'],
            'blocks' => ['userid', 'blockid'],
            'posts' => ['userid', 'editedby'],
            'topics' => ['userid'],
            'readposts' => ['userid'],
            'pollanswers' => ['userid'],
            'offervotes' => ['userid'],
            'iplog' => ['userid'],
            'messages' => ['sender', 'receiver'],
            'news' => ['userid'],
            'offers' => ['userid'],
            'user_passkeys' => ['user_id'],
        ];
        foreach ($mediumToBigint as $table => $columns) {
            foreach ($columns as $col) {
                DB::statement("ALTER TABLE `$table` MODIFY `$col` BIGINT UNSIGNED NOT NULL DEFAULT 0");
            }
        }

        // int → bigint unsigned (for uid columns referencing users.id)
        $intToBigint = [
            'hit_and_runs' => ['uid'],
            'torrent_buy_logs' => ['uid'],
            'torrent_operation_logs' => ['uid'],
            'torrent_secrets' => ['uid'],
            'exam_users' => ['uid'],
            'exam_progress' => ['uid'],
            'user_medals' => ['uid'],
            'user_metas' => ['uid'],
            'bonus_logs' => ['uid'],
            'login_logs' => ['uid'],
        ];
        foreach ($intToBigint as $table => $columns) {
            foreach ($columns as $col) {
                DB::statement("ALTER TABLE `$table` MODIFY `$col` BIGINT UNSIGNED NOT NULL DEFAULT 0");
            }
        }

        // int unsigned → bigint unsigned
        DB::statement('ALTER TABLE `attendance` MODIFY `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `shoutbox_reactions` MODIFY `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // torrents.id = mediumint unsigned → child torrent_id columns: int → mediumint unsigned
        $intToMedium = [
            'hit_and_runs' => ['torrent_id'],
            'torrent_buy_logs' => ['torrent_id'],
            'torrent_operation_logs' => ['torrent_id'],
            'torrent_secrets' => ['torrent_id'],
            'torrent_tags' => ['torrent_id'],
        ];
        foreach ($intToMedium as $table => $columns) {
            foreach ($columns as $col) {
                DB::statement("ALTER TABLE `$table` MODIFY `$col` MEDIUMINT UNSIGNED NOT NULL DEFAULT 0");
            }
        }

        // exam_progress.torrent_id: int → mediumint unsigned, make nullable
        DB::statement('ALTER TABLE `exam_progress` MODIFY `torrent_id` MEDIUMINT UNSIGNED NULL DEFAULT NULL');

        // tags.id = bigint unsigned → torrent_tags.tag_id: int → bigint unsigned
        DB::statement('ALTER TABLE `torrent_tags` MODIFY `tag_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // exams.id = bigint unsigned → exam_id columns: int → bigint unsigned
        DB::statement('ALTER TABLE `exam_users` MODIFY `exam_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `exam_progress` MODIFY `exam_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // exam_users.id = bigint unsigned → exam_progress.exam_user_id: int → bigint unsigned
        DB::statement('ALTER TABLE `exam_progress` MODIFY `exam_user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // medals.id = bigint unsigned → user_medals.medal_id: int → bigint unsigned
        DB::statement('ALTER TABLE `user_medals` MODIFY `medal_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // snatched.id = bigint unsigned → hit_and_runs.snatched_id: int → bigint unsigned
        DB::statement('ALTER TABLE `hit_and_runs` MODIFY `snatched_id` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        // shoutbox.id = int → shoutbox_reactions.shoutbox_id: int unsigned → int
        DB::statement('ALTER TABLE `shoutbox_reactions` MODIFY `shoutbox_id` INT NOT NULL DEFAULT 0');

        // messages.sender: make nullable (for system messages with NULL sender)
        DB::statement('ALTER TABLE `messages` MODIFY `sender` BIGINT UNSIGNED NULL DEFAULT NULL');

        // posts.editedby: make nullable (for SET NULL FK, 0 → NULL)
        DB::table('posts')->where('editedby', 0)->update(['editedby' => null]);
        DB::statement('ALTER TABLE `posts` MODIFY `editedby` BIGINT UNSIGNED NULL DEFAULT NULL');

        // ──────────────────────────────────────────────────────────────
        // Step 3: Add foreign keys with ON DELETE CASCADE / SET NULL
        // ──────────────────────────────────────────────────────────────

        // → users.id ON DELETE CASCADE
        Schema::table('bookmarks', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('snatched', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('peers', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('comments', function ($t) {
            $t->foreign('user')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('thanks', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('hit_and_runs', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('torrent_buy_logs', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('torrent_operation_logs', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('torrent_secrets', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('exam_users', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('exam_progress', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('user_medals', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('user_metas', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('bonus_logs', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('attendance', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('login_logs', function ($t) {
            $t->foreign('uid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('friends', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('friends', function ($t) {
            $t->foreign('friendid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('blocks', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('blocks', function ($t) {
            $t->foreign('blockid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('shoutbox_reactions', function ($t) {
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('posts', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('readposts', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('pollanswers', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('offervotes', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('iplog', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('news', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('offers', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('user_passkeys', function ($t) {
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // → users.id ON DELETE SET NULL (nullable columns)
        Schema::table('messages', function ($t) {
            $t->foreign('sender')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('messages', function ($t) {
            $t->foreign('receiver')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::table('posts', function ($t) {
            $t->foreign('editedby')->references('id')->on('users')->onDelete('set null');
        });
        Schema::table('topics', function ($t) {
            $t->foreign('userid')->references('id')->on('users')->onDelete('cascade');
        });

        // → torrents.id ON DELETE CASCADE
        Schema::table('hit_and_runs', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });
        Schema::table('torrent_buy_logs', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });
        Schema::table('torrent_operation_logs', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });
        Schema::table('torrent_secrets', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });
        Schema::table('torrent_tags', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });
        Schema::table('exam_progress', function ($t) {
            $t->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
        });

        // → tags.id ON DELETE CASCADE
        Schema::table('torrent_tags', function ($t) {
            $t->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });

        // → exams.id ON DELETE CASCADE
        Schema::table('exam_users', function ($t) {
            $t->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });
        Schema::table('exam_progress', function ($t) {
            $t->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });

        // → exam_users.id ON DELETE CASCADE
        Schema::table('exam_progress', function ($t) {
            $t->foreign('exam_user_id')->references('id')->on('exam_users')->onDelete('cascade');
        });

        // → medals.id ON DELETE CASCADE
        Schema::table('user_medals', function ($t) {
            $t->foreign('medal_id')->references('id')->on('medals')->onDelete('cascade');
        });

        // → snatched.id ON DELETE CASCADE
        Schema::table('hit_and_runs', function ($t) {
            $t->foreign('snatched_id')->references('id')->on('snatched')->onDelete('cascade');
        });

        // → shoutbox.id ON DELETE CASCADE
        Schema::table('shoutbox_reactions', function ($t) {
            $t->foreign('shoutbox_id')->references('id')->on('shoutbox')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all FKs added in step 3
        $fks = [
            ['shoutbox_reactions', 'shoutbox_id'],
            ['hit_and_runs', 'snatched_id'],
            ['user_medals', 'medal_id'],
            ['exam_progress', 'exam_user_id'],
            ['exam_progress', 'exam_id'],
            ['exam_users', 'exam_id'],
            ['torrent_tags', 'tag_id'],
            ['exam_progress', 'torrent_id'],
            ['torrent_tags', 'torrent_id'],
            ['torrent_secrets', 'torrent_id'],
            ['torrent_operation_logs', 'torrent_id'],
            ['torrent_buy_logs', 'torrent_id'],
            ['hit_and_runs', 'torrent_id'],
            ['topics', 'userid'],
            ['posts', 'editedby'],
            ['messages', 'receiver'],
            ['messages', 'sender'],
            ['user_passkeys', 'user_id'],
            ['offers', 'userid'],
            ['news', 'userid'],
            ['iplog', 'userid'],
            ['offervotes', 'userid'],
            ['pollanswers', 'userid'],
            ['readposts', 'userid'],
            ['posts', 'userid'],
            ['shoutbox_reactions', 'user_id'],
            ['blocks', 'blockid'],
            ['blocks', 'userid'],
            ['friends', 'friendid'],
            ['friends', 'userid'],
            ['login_logs', 'uid'],
            ['attendance', 'uid'],
            ['bonus_logs', 'uid'],
            ['user_metas', 'uid'],
            ['user_medals', 'uid'],
            ['exam_progress', 'uid'],
            ['exam_users', 'uid'],
            ['torrent_secrets', 'uid'],
            ['torrent_operation_logs', 'uid'],
            ['torrent_buy_logs', 'uid'],
            ['hit_and_runs', 'uid'],
            ['thanks', 'userid'],
            ['comments', 'user'],
            ['peers', 'userid'],
            ['snatched', 'userid'],
            ['bookmarks', 'userid'],
        ];
        foreach ($fks as [$table, $col]) {
            Schema::table($table, function ($t) use ($col) {
                $t->dropForeign([$col]);
            });
        }
    }
};
