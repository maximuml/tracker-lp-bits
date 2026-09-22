<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\User;
use App\Repositories\NotificationFeedRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Covers notification_cursors persistence plus the channel queries for
 * comments-on-own-torrents and posts-in-own-topics.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class NotificationFeedRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private NotificationFeedRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('notification_cursors')->delete();
        DB::table('messages')->delete();
        DB::table('shoutbox')->delete();
        DB::table('comments')->delete();
        DB::table('posts')->delete();
        DB::table('topics')->delete();
        DB::table('forums')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new NotificationFeedRepository;
    }

    private function createUser(string $username): int
    {
        /** @var User $user */
        $user = User::factory()->create(['username' => $username]);

        return (int) $user->id;
    }

    private function createTorrent(int $ownerId): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Feed Torrent',
            'filename' => 'feed.torrent',
            'save_as' => 'feed',
            'category' => 1,
            'size' => 1024,
            'type' => 0,
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);
    }

    private function createComment(int $torrentId, int $userId): int
    {
        return (int) DB::table('comments')->insertGetId([
            'user' => $userId,
            'torrent' => $torrentId,
            'added' => now()->toDateTimeString(),
            'text' => 'comment body',
            'ori_text' => 'comment body',
            'offer' => 0,
            'request' => 0,
            'anonymous' => 0,
        ]);
    }

    private function createForum(int $minClassRead = 0): int
    {
        return (int) DB::table('forums')->insertGetId([
            'name' => 'Feed Forum',
            'minclassread' => $minClassRead,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
    }

    private function createTopic(int $userId, ?int $forumId = null): int
    {
        return (int) DB::table('topics')->insertGetId([
            'userid' => $userId,
            'subject' => 'Feed topic',
            'forumid' => $forumId ?? $this->createForum(),
        ]);
    }

    private function createPost(int $topicId, int $userId): int
    {
        return (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $userId,
            'added' => now()->toDateTimeString(),
            'body' => 'post body',
            'ori_body' => 'post body',
        ]);
    }

    public function test_saved_cursors_default_to_zero_and_round_trip(): void
    {
        $cursors = $this->repository->savedCursors(42);
        foreach (NotificationFeedRepository::CHANNELS as $channel) {
            $this->assertArrayHasKey($channel, $cursors);
            $this->assertSame(0, $cursors[$channel]);
        }

        $this->repository->saveCursors(42, ['pm' => 7, 'staff' => 3, 'bogus_channel' => 99]);
        $cursors = $this->repository->savedCursors(42);
        $this->assertSame(7, $cursors['pm']);
        $this->assertSame(3, $cursors['staff']);
        $this->assertSame(0, $cursors['shout']);
        $this->assertSame(0, DB::table('notification_cursors')->where('channel', 'bogus_channel')->count());

        $this->repository->saveCursors(42, ['pm' => 9]);
        $this->assertSame(9, $this->repository->savedCursors(42)['pm']);
    }

    public function test_channel_maxes_reflect_source_rows(): void
    {
        $owner = $this->createUser('owner1');
        $other = $this->createUser('other1');
        $torrentId = $this->createTorrent($owner);
        $topicId = $this->createTopic($owner);

        $commentId = $this->createComment($torrentId, $other);
        $postId = $this->createPost($topicId, $other);
        $messageId = (int) DB::table('messages')->insertGetId([
            'sender' => $other,
            'receiver' => $owner,
            'added' => now()->toDateTimeString(),
            'subject' => 'hi',
            'msg' => 'body',
            'unread' => 1,
            'location' => 1,
        ]);
        $shoutId = (int) DB::table('shoutbox')->insertGetId([
            'userid' => $other,
            'date' => time(),
            'text' => 'shout',
        ]);

        $maxes = $this->repository->channelMaxes($owner);
        $this->assertSame($messageId, $maxes['pm']);
        $this->assertSame($shoutId, $maxes['shout']);
        $this->assertSame($commentId, $maxes['comment']);
        $this->assertSame($postId, $maxes['topic_reply']);
        $this->assertArrayNotHasKey('staff', $maxes);
    }

    public function test_unread_counts_only_rows_newer_than_cursor_and_not_own(): void
    {
        $owner = $this->createUser('owner2');
        $other = $this->createUser('other2');
        $torrentId = $this->createTorrent($owner);
        $topicId = $this->createTopic($owner);

        $first = $this->createComment($torrentId, $other);
        $this->createComment($torrentId, $owner); // own comment — excluded
        $this->createPost($topicId, $other);
        DB::table('messages')->insert([
            'sender' => $other,
            'receiver' => $owner,
            'added' => now()->toDateTimeString(),
            'subject' => 'hi',
            'msg' => 'body',
            'unread' => 1,
            'location' => 1,
        ]);

        $counts = $this->repository->unreadCounts($owner, ['pm' => 0, 'comment' => $first, 'topic_reply' => 0]);
        $this->assertSame(1, $counts['pm']);
        $this->assertSame(0, $counts['comment']);
        $this->assertSame(1, $counts['topic_reply']);
    }

    public function test_comment_and_post_item_queries_return_rows_with_names(): void
    {
        $owner = $this->createUser('owner3');
        $other = $this->createUser('other3');
        $torrentId = $this->createTorrent($owner);
        $topicId = $this->createTopic($owner);
        $commentId = $this->createComment($torrentId, $other);
        $postId = $this->createPost($topicId, $other);

        $comments = $this->repository->newCommentsOnOwnTorrents($owner, 0);
        $this->assertCount(1, $comments);
        $this->assertSame($commentId, (int) $comments[0]['id']);
        $this->assertSame('Feed Torrent', $comments[0]['torrent_name']);
        $this->assertSame('other3', $comments[0]['author_name']);
        $this->assertArrayHasKey('ts', $comments[0]);

        $posts = $this->repository->newPostsInOwnTopics($owner, 0);
        $this->assertCount(1, $posts);
        $this->assertSame($postId, (int) $posts[0]['id']);
        $this->assertSame('Feed topic', $posts[0]['topic_subject']);
        $this->assertSame('other3', $posts[0]['author_name']);

        $this->assertSame([], $this->repository->newCommentsOnOwnTorrents($owner, $commentId));
        $this->assertSame([], $this->repository->newPostsInOwnTopics($owner, $postId));
    }
}
