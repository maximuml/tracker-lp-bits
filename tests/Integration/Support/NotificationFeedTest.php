<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Models\User;
use App\Repositories\NotificationFeedRepository;
use App\Support\NotificationFeed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * REL-01: notification feed cursor model — delivery cursors must not jump
 * past undelivered items, read cursors must be monotonic and snapshot-
 * based, and the badge must agree with the list.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class NotificationFeedTest extends TestCase
{
    use DatabaseTransactions;

    private NotificationFeed $feed;

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
        DB::table('torrents')->delete();
        DB::table('staffmessages')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new NotificationFeedRepository;
        $this->feed = app(NotificationFeed::class);
    }

    private function createUser(string $username, int $class = 1): int
    {
        /** @var User $user */
        $user = User::factory()->create(['username' => $username, 'class' => $class]);

        return (int) $user->id;
    }

    private function createPm(int $receiverId, int $senderId, ?int $addedTs = null): int
    {
        return (int) DB::table('messages')->insertGetId([
            'sender' => $senderId,
            'receiver' => $receiverId,
            'added' => $addedTs !== null
                ? date('Y-m-d H:i:s', $addedTs)
                : now()->toDateTimeString(),
            'subject' => 'hi',
            'msg' => 'body',
            'unread' => 1,
            'location' => 1,
        ]);
    }

    private function createShout(int $authorId, string $text): int
    {
        return (int) DB::table('shoutbox')->insertGetId([
            'userid' => $authorId,
            'date' => time(),
            'text' => $text,
            'type' => 0,
        ]);
    }

    private function createTorrent(int $ownerId, int $visible = 1, int $banned = 0): int
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
            'visible' => $visible,
            'banned' => $banned,
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

    private function createForum(int $minClassRead): int
    {
        return (int) DB::table('forums')->insertGetId([
            'name' => 'Forum '.$minClassRead,
            'minclassread' => $minClassRead,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
    }

    private function createTopic(int $userId, int $forumId): int
    {
        return (int) DB::table('topics')->insertGetId([
            'userid' => $userId,
            'subject' => 'Feed topic',
            'forumid' => $forumId,
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

    /** @return array<string, int> */
    private function zeroCursors(): array
    {
        return ['pm' => 0, 'shout' => 0, 'comment' => 0, 'topic_reply' => 0, 'staff' => 0];
    }

    /**
     * Drains since() like a polling client: feeds each returned cursor set
     * back into the next call until nothing arrives.
     *
     * @return array{0: list<string>, 1: array<string, int>}
     */
    private function drainSince(int $userId): array
    {
        $cursors = $this->zeroCursors();
        $delivered = [];
        for ($i = 0; $i < 20; $i++) {
            $data = $this->feed->since($userId, $cursors);
            foreach ($data['notifications'] as $n) {
                $delivered[] = (string) $n['id'];
            }
            if ($data['cursors'] === $cursors && $data['notifications'] === []) {
                break;
            }
            $cursors = $data['cursors'];
        }

        return [$delivered, $cursors];
    }

    public function test_since_delivers_full_backlog_without_skips(): void
    {
        $owner = $this->createUser('pk_owner');
        $sender = $this->createUser('pk_sender');

        $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $ids[] = $this->createPm($owner, $sender);
        }

        // The old code returned source maxes as cursors after a 20-item
        // slice — 30 PMs vanished forever on the first poll.
        [$delivered, $cursors] = $this->drainSince($owner);

        $expected = array_map(static fn (int $id): string => 'pm_'.$id, $ids);
        $actual = $delivered;
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertSame(max($ids), $cursors['pm']);
    }

    public function test_since_advances_cursor_only_over_delivered_prefix(): void
    {
        $owner = $this->createUser('pfx_owner');
        $sender = $this->createUser('pfx_sender');

        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->createPm($owner, $sender);
        }

        $data = $this->feed->since($owner, $this->zeroCursors());
        $this->assertCount(20, $data['notifications']);
        // Cursor must point at the last delivered id — the 5 undelivered
        // tail items stay > cursor and arrive on the next poll.
        $this->assertSame($ids[19], $data['cursors']['pm']);
        $this->assertTrue($data['has_more']);

        $data2 = $this->feed->since($owner, $data['cursors']);
        $this->assertCount(5, $data2['notifications']);
        $this->assertSame($ids[24], $data2['cursors']['pm']);
        $this->assertFalse($data2['has_more']);
    }

    public function test_since_mixed_channels_stable_order(): void
    {
        $owner = $this->createUser('mix_owner');
        $other = $this->createUser('mix_other');
        $ts = time() - 60;

        // Same timestamp across channels — order must be deterministic.
        $pmId = $this->createPm($owner, $other, $ts);
        $torrentId = $this->createTorrent($owner);
        DB::table('comments')->insertGetId([
            'user' => $other,
            'torrent' => $torrentId,
            'added' => date('Y-m-d H:i:s', $ts),
            'text' => 'c', 'ori_text' => 'c', 'offer' => 0, 'request' => 0, 'anonymous' => 0,
        ]);
        $this->createShout($other, 'hello @mix_owner !');

        $first = $this->feed->since($owner, $this->zeroCursors());
        $second = $this->feed->since($owner, $this->zeroCursors());

        $order1 = array_map(static fn (array $n): string => (string) $n['id'], $first['notifications']);
        $order2 = array_map(static fn (array $n): string => (string) $n['id'], $second['notifications']);
        $this->assertSame($order1, $order2);
        $this->assertContains('pm_'.$pmId, $order1);
        $this->assertCount(3, $order1);
    }

    public function test_unread_does_not_reseed_all_zero_cursors(): void
    {
        $owner = $this->createUser('seed_owner');
        $sender = $this->createUser('seed_sender');

        // First call seeds cursors — with an empty feed every channel is 0.
        $this->feed->unread($owner);
        $this->assertTrue($this->repository->hasCursors($owner));

        // A PM arriving after the seed must show up — previously the
        // array_sum===0 check re-seeded and silently swallowed it.
        $this->createPm($owner, $sender);

        $data = $this->feed->unread($owner);
        $this->assertSame(1, $data['counts']['pm']);
        $this->assertSame(1, $data['counts']['total']);
        $this->assertCount(1, $data['items']);
    }

    public function test_mark_all_read_snapshot_keeps_later_events(): void
    {
        $owner = $this->createUser('snap_owner');
        $sender = $this->createUser('snap_sender');
        $this->feed->unread($owner); // seed empty feed

        $this->createPm($owner, $sender);
        $panel = $this->feed->unread($owner);
        $this->assertSame(1, $panel['counts']['pm']);

        // A second PM lands while the panel is open; marking read with the
        // panel's watermark must leave it unread.
        $this->createPm($owner, $sender);
        $this->feed->markAllRead($owner, $panel['watermark']);

        $after = $this->feed->unread($owner);
        $this->assertSame(1, $after['counts']['pm']);
        $this->assertCount(1, $after['items']);
    }

    public function test_mark_all_read_without_watermark_marks_everything(): void
    {
        $owner = $this->createUser('all_owner');
        $sender = $this->createUser('all_sender');
        $this->feed->unread($owner); // seed empty feed
        $this->createPm($owner, $sender);
        $this->createPm($owner, $sender);

        $this->feed->unread($owner);
        $this->feed->markAllRead($owner);

        $this->assertSame(0, $this->feed->unread($owner)['counts']['pm']);
    }

    public function test_mark_all_read_watermark_is_clamped_to_source_max(): void
    {
        $owner = $this->createUser('clamp_owner');
        $sender = $this->createUser('clamp_sender');
        $this->feed->unread($owner); // seed empty feed
        $pmId = $this->createPm($owner, $sender);
        $this->feed->unread($owner);

        // A forged far-future watermark must not mark not-yet-existing
        // rows read — clamp to the real source max.
        $this->feed->markAllRead($owner, ['pm' => $pmId + 100000]);

        $saved = $this->repository->savedCursors($owner);
        $this->assertSame($pmId, $saved['pm']);
    }

    public function test_cursors_are_monotonic(): void
    {
        $owner = $this->createUser('mono_owner');

        $this->repository->saveCursors($owner, ['pm' => 10]);
        $this->repository->saveCursors($owner, ['pm' => 5]);

        $this->assertSame(10, $this->repository->savedCursors($owner)['pm']);
    }

    public function test_unread_pagination_reaches_whole_backlog(): void
    {
        $owner = $this->createUser('page_owner');
        $sender = $this->createUser('page_sender');
        $this->feed->unread($owner); // seed empty feed
        for ($i = 0; $i < 45; $i++) {
            $this->createPm($owner, $sender);
        }

        $seen = [];
        $offset = 0;
        $pages = 0;
        do {
            $data = $this->feed->unread($owner, $offset);
            foreach ($data['items'] as $item) {
                $seen[] = (string) $item['id'];
            }
            $offset += count($data['items']);
            $pages++;
        } while ($data['has_more'] && $pages < 10);

        $this->assertCount(45, $seen);
        $this->assertSame(45, count(array_unique($seen)));
        $this->assertSame(45, $data['counts']['pm']);
    }

    public function test_unread_delivers_mentions_past_nonzero_cursor(): void
    {
        $owner = $this->createUser('desc_owner');
        $sender = $this->createUser('desc_sender');

        // An earlier mention sits under the cursor — the panel view walks
        // mentions newest-first, and the desc iterator used to seed its
        // boundary from $lastShoutId (id > X AND id < X → empty set).
        $old = $this->createShout($sender, 'earlier @desc_owner ping');
        $this->repository->saveCursors($owner, ['shout' => $old]);

        $new = $this->createShout($sender, 'fresh @desc_owner ping');

        $data = $this->feed->unread($owner);

        $this->assertSame(1, $data['counts']['shout']);
        $ids = array_map(static fn (array $n): string => (string) $n['id'], $data['items']);
        $this->assertContains('shout_'.$new, $ids);
    }

    public function test_unread_pagination_parity_with_mixed_channels(): void
    {
        $owner = $this->createUser('par_owner');
        $sender = $this->createUser('par_sender');
        $this->feed->unread($owner); // seed empty feed

        // 22 PMs push the merged feed past one page; the mention,
        // comment and reply land in the tail — if any channel counts a
        // row its item query never yields, has_more never terminates.
        $expected = 22 + 3;
        for ($i = 0; $i < 22; $i++) {
            $this->createPm($owner, $sender);
        }
        $torrentId = $this->createTorrent($owner);
        $this->createComment($torrentId, $sender);
        $topicId = $this->createTopic($owner, $this->createForum(0));
        $this->createPost($topicId, $sender);
        $this->createShout($sender, 'tail @par_owner ping');

        $seen = [];
        $offset = 0;
        $pages = 0;
        $data = ['items' => [], 'has_more' => true, 'counts' => ['total' => 0]];
        do {
            $data = $this->feed->unread($owner, $offset);
            foreach ($data['items'] as $item) {
                $seen[] = (string) $item['id'];
            }
            $offset += count($data['items']);
        } while ($data['has_more'] && ++$pages < 10);

        $this->assertSame($expected, $data['counts']['total']);
        $this->assertCount($expected, $seen);
        $this->assertFalse($data['has_more']);
    }

    public function test_badge_total_matches_deliverable_items(): void
    {
        $owner = $this->createUser('badge_owner');
        $sender = $this->createUser('badge_sender');
        $this->feed->unread($owner); // seed empty feed
        $torrentId = $this->createTorrent($owner);
        $forumId = $this->createForum(0);

        $this->createPm($owner, $sender);
        $this->createPm($owner, $sender);
        $this->createComment($torrentId, $sender);
        $topicId = $this->createTopic($owner, $forumId);
        $this->createPost($topicId, $sender);
        $this->createShout($sender, 'ping @badge_owner');

        $data = $this->feed->unread($owner);

        $this->assertSame(5, $data['counts']['total']);
        $this->assertSame(5, count($data['items']));
        $this->assertSame(1, $data['counts']['shout']);
    }

    public function test_mention_like_hits_without_regex_match_do_not_hide_mentions(): void
    {
        $owner = $this->createUser('mention_target');
        $sender = $this->createUser('mention_sender');
        $this->feed->unread($owner); // seed empty feed

        // LIKE prefilter hits but regex rejects (no word boundary).
        for ($i = 0; $i < 60; $i++) {
            $this->createShout($sender, 'hi @mention_target99 there');
        }
        $real = $this->createShout($sender, 'real ping @mention_target !');

        $data = $this->feed->unread($owner);

        $this->assertSame(1, $data['counts']['shout']);
        $mentionIds = array_map(
            static fn (array $n): string => (string) $n['id'],
            array_filter($data['items'], static fn (array $n): bool => $n['type'] === 'shoutbox-mention'),
        );
        $this->assertSame(['shout_'.$real], array_values($mentionIds));
    }

    public function test_topic_reply_link_uses_forums_viewtopic_action(): void
    {
        $owner = $this->createUser('link_owner');
        $other = $this->createUser('link_other');
        $this->feed->unread($owner); // seed empty feed
        $forumId = $this->createForum(0);
        $topicId = $this->createTopic($owner, $forumId);
        $this->createPost($topicId, $other);

        $data = $this->feed->unread($owner);

        $reply = null;
        foreach ($data['items'] as $item) {
            if ($item['type'] === 'topic_reply') {
                $reply = $item;
            }
        }
        $this->assertNotNull($reply);
        $this->assertSame('forums.php?action=viewtopic&topicid='.$topicId.'&page=last', $reply['url']);
    }

    public function test_topic_reply_hidden_when_forum_above_user_class(): void
    {
        $owner = $this->createUser('perm_owner', 1); // USER class
        $other = $this->createUser('perm_other');
        $this->feed->unread($owner); // seed empty feed

        $openForum = $this->createForum(0);
        $staffForum = $this->createForum(90); // above the owner's class
        $openTopic = $this->createTopic($owner, $openForum);
        $staffTopic = $this->createTopic($owner, $staffForum);
        $this->createPost($openTopic, $other);
        $this->createPost($staffTopic, $other);

        $data = $this->feed->unread($owner);

        // The staff-forum reply must not leak — not in items, not in badge.
        $this->assertSame(1, $data['counts']['topic_reply']);
        $replyTopics = array_map(
            static fn (array $n): string => (string) ($n['context'] ?? ''),
            array_filter($data['items'], static fn (array $n): bool => $n['type'] === 'topic_reply'),
        );
        $this->assertCount(1, $replyTopics);
    }

    public function test_comment_on_hidden_or_banned_torrent_not_notified(): void
    {
        $owner = $this->createUser('torr_owner');
        $other = $this->createUser('torr_other');
        $this->feed->unread($owner); // seed empty feed

        $hidden = $this->createTorrent($owner, 0, 0);
        $banned = $this->createTorrent($owner, 1, 1);
        $normal = $this->createTorrent($owner, 1, 0);
        $this->createComment($hidden, $other);
        $this->createComment($banned, $other);
        $this->createComment($normal, $other);

        $data = $this->feed->unread($owner);

        $this->assertSame(1, $data['counts']['comment']);
        $comments = array_filter($data['items'], static fn (array $n): bool => $n['type'] === 'comment');
        $this->assertCount(1, $comments);
    }

    // ---- REL-02: hasNewerThan idle probe ----

    public function test_probe_is_false_when_no_source_moved(): void
    {
        $user = $this->createUser('probe_user');

        $this->assertFalse($this->feed->hasNewerThan($user, [
            'pm' => 0, 'shout' => 0, 'comment' => 0, 'topic_reply' => 0, 'staff' => 0,
        ]));
    }

    public function test_probe_detects_new_source_row(): void
    {
        $user = $this->createUser('probe_user2');
        $sender = $this->createUser('probe_sender2');
        $pmId = $this->createPm($user, $sender);

        $this->assertTrue($this->feed->hasNewerThan($user, [
            'pm' => 0, 'shout' => 0, 'comment' => 0, 'topic_reply' => 0, 'staff' => 0,
        ]));
        // Cursor at the source max — nothing new to deliver.
        $this->assertFalse($this->feed->hasNewerThan($user, [
            'pm' => $pmId, 'shout' => 0, 'comment' => 0, 'topic_reply' => 0, 'staff' => 0,
        ]));
    }

    public function test_probe_covers_every_channel(): void
    {
        $user = $this->createUser('probe_user3');
        $other = $this->createUser('probe_other3');
        $cursors = ['pm' => PHP_INT_MAX, 'shout' => 0, 'comment' => 0, 'topic_reply' => 0, 'staff' => 0];

        // A new shout anywhere — even one that does not mention the
        // user — trips the probe (false positives are cheap, false
        // negatives lose events).
        $this->createShout($other, 'unrelated text');
        $this->assertTrue($this->feed->hasNewerThan($user, $cursors));
    }
}
