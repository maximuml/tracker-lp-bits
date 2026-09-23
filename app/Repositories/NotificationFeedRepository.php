<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Notification center data: per-channel read cursors (notification_cursors)
 * plus item/count queries for channels not owned by another repository
 * (comments on own torrents, replies in own forum topics).
 */
final class NotificationFeedRepository extends BaseRepository
{
    public const CHANNELS = ['pm', 'shout', 'comment', 'topic_reply', 'staff'];

    /**
     * @return array<string, int> channel => last read id (missing channels => 0)
     */
    public function savedCursors(int $userId): array
    {
        $rows = DB::table('notification_cursors')
            ->where('user_id', $userId)
            ->pluck('last_id', 'channel');

        $cursors = [];
        foreach (self::CHANNELS as $channel) {
            $cursors[$channel] = (int) ($rows[$channel] ?? 0);
        }

        return $cursors;
    }

    /**
     * Whether the user has any cursor rows at all. This is the "not
     * initialized yet" signal — a row with last_id = 0 is a real cursor
     * (user had nothing to read), while a missing row means the feed has
     * never been seeded for this user.
     */
    public function hasCursors(int $userId): bool
    {
        return DB::table('notification_cursors')
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Current max source-row id per channel for this user.
     *
     * @return array<string, int>
     */
    public function channelMaxes(int $userId): array
    {
        return [
            'pm' => (int) (DB::table('messages')->where('receiver', $userId)->max('id') ?? 0),
            'shout' => (int) (DB::table('shoutbox')->max('id') ?? 0),
            'comment' => (int) DB::table('comments')
                ->join('torrents', 'comments.torrent', '=', 'torrents.id')
                ->where('torrents.owner', $userId)
                ->max('comments.id'),
            'topic_reply' => (int) DB::table('posts')
                ->join('topics', 'posts.topicid', '=', 'topics.id')
                ->where('topics.userid', $userId)
                ->max('posts.id'),
        ];
    }

    /**
     * Raw source maxima without the ownership/permission joins — the
     * cheap "anything new?" probe for the SSE loop. Deliberately broader
     * than channelMaxes(): a false positive only costs one regular
     * since() pass, while a false negative would silently drop events.
     * One round trip, five primary-key lookups.
     *
     * @return array<string, int>
     */
    public function sourceMaxima(int $userId): array
    {
        $rows = DB::select(
            'SELECT '
            .'(SELECT MAX(id) FROM messages WHERE receiver = ?) AS pm, '
            .'(SELECT MAX(id) FROM shoutbox) AS shout, '
            .'(SELECT MAX(id) FROM comments) AS comment, '
            .'(SELECT MAX(id) FROM posts) AS topic_reply, '
            .'(SELECT MAX(id) FROM staffmessages) AS staff',
            [$userId]
        );
        $row = $rows[0] ?? null;

        return [
            'pm' => (int) ($row->pm ?? 0),
            'shout' => (int) ($row->shout ?? 0),
            'comment' => (int) ($row->comment ?? 0),
            'topic_reply' => (int) ($row->topic_reply ?? 0),
            'staff' => (int) ($row->staff ?? 0),
        ];
    }

    /**
     * Monotonic cursor update: a concurrent stale request must never move
     * last_id backwards, so the write is a single upsert guarded by
     * GREATEST() rather than a plain updateOrInsert.
     *
     * @param  array<string, int>  $maxes
     */
    public function saveCursors(int $userId, array $maxes): void
    {
        foreach ($maxes as $channel => $lastId) {
            if (! in_array($channel, self::CHANNELS, true)) {
                continue;
            }
            DB::statement(
                'INSERT INTO notification_cursors (user_id, channel, last_id) VALUES (?, ?, ?) '
                .'ON DUPLICATE KEY UPDATE last_id = GREATEST(last_id, VALUES(last_id))',
                [$userId, $channel, (int) $lastId]
            );
        }
    }

    /**
     * Unread counts per channel (rows newer than the given cursors).
     * Comments count only on visible, non-banned torrents; topic replies
     * only in forums the user can read — same contract as the item
     * queries, so the badge and the list cannot diverge.
     *
     * @param  array<string, int>  $cursors
     * @return array<string, int>
     */
    public function unreadCounts(int $userId, array $cursors, int $userClass = 0): array
    {
        $commentCursor = (int) ($cursors['comment'] ?? 0);
        $replyCursor = (int) ($cursors['topic_reply'] ?? 0);

        return [
            'pm' => DB::table('messages')
                ->where('receiver', $userId)
                ->where('unread', 1)
                ->where('id', '>', (int) ($cursors['pm'] ?? 0))
                ->count(),
            'comment' => DB::table('comments')
                ->join('torrents', 'comments.torrent', '=', 'torrents.id')
                ->where('torrents.owner', $userId)
                ->where('torrents.visible', 1)
                ->where('torrents.banned', 0)
                ->where('comments.user', '!=', $userId)
                ->where('comments.id', '>', $commentCursor)
                ->count(),
            'topic_reply' => DB::table('posts')
                ->join('topics', 'posts.topicid', '=', 'topics.id')
                ->join('forums', 'topics.forumid', '=', 'forums.id')
                ->where('topics.userid', $userId)
                ->where('posts.userid', '!=', $userId)
                ->where('posts.id', '>', $replyCursor)
                ->where('forums.minclassread', '<=', $userClass)
                ->count(),
        ];
    }

    /**
     * Comments on the user's own torrents newer than the cursor.
     * Hidden/banned torrents are excluded — the notification must not
     * reveal an object the owner can no longer see.
     *
     * @return list<array<string, mixed>>
     */
    public function newCommentsOnOwnTorrents(int $userId, int $lastId, int $limit = 20, bool $oldestFirst = true): array
    {
        return array_values(DB::table('comments')
            ->join('torrents', 'comments.torrent', '=', 'torrents.id')
            ->leftJoin('users', 'comments.user', '=', 'users.id')
            ->where('torrents.owner', $userId)
            ->where('torrents.visible', 1)
            ->where('torrents.banned', 0)
            ->where('comments.user', '!=', $userId)
            ->where('comments.id', '>', $lastId)
            ->orderBy('comments.id', $oldestFirst ? 'asc' : 'desc')
            ->limit($limit)
            ->get([
                'comments.id',
                'comments.text',
                'comments.torrent',
                'torrents.name as torrent_name',
                'users.username as author_name',
                DB::raw('UNIX_TIMESTAMP(comments.added) as ts'),
            ])
            ->map(fn ($row) => (array) $row)
            ->all());
    }

    /**
     * Posts in topics the user started, newer than the cursor. Only
     * forums readable at the user's class are included — a notification
     * must not leak activity in a forum the user lost access to.
     *
     * @return list<array<string, mixed>>
     */
    public function newPostsInOwnTopics(int $userId, int $lastId, int $limit = 20, bool $oldestFirst = true, int $userClass = 0): array
    {
        return array_values(DB::table('posts')
            ->join('topics', 'posts.topicid', '=', 'topics.id')
            ->join('forums', 'topics.forumid', '=', 'forums.id')
            ->leftJoin('users', 'posts.userid', '=', 'users.id')
            ->where('topics.userid', $userId)
            ->where('posts.userid', '!=', $userId)
            ->where('posts.id', '>', $lastId)
            ->where('forums.minclassread', '<=', $userClass)
            ->orderBy('posts.id', $oldestFirst ? 'asc' : 'desc')
            ->limit($limit)
            ->get([
                'posts.id',
                'posts.body',
                'posts.topicid',
                'topics.subject as topic_subject',
                'users.username as author_name',
                DB::raw('UNIX_TIMESTAMP(posts.added) as ts'),
            ])
            ->map(fn ($row) => (array) $row)
            ->all());
    }
}
