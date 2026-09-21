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
    public const CHANNELS = ['pm', 'shout', 'comment', 'topic_reply'];

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
     * @param  array<string, int>  $maxes
     */
    public function saveCursors(int $userId, array $maxes): void
    {
        foreach ($maxes as $channel => $lastId) {
            if (! in_array($channel, self::CHANNELS, true)) {
                continue;
            }
            DB::table('notification_cursors')->updateOrInsert(
                ['user_id' => $userId, 'channel' => $channel],
                ['last_id' => (int) $lastId]
            );
        }
    }

    /**
     * Unread counts per channel (rows newer than the given cursors).
     *
     * @param  array<string, int>  $cursors
     * @return array<string, int>
     */
    public function unreadCounts(int $userId, array $cursors): array
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
                ->where('comments.user', '!=', $userId)
                ->where('comments.id', '>', $commentCursor)
                ->count(),
            'topic_reply' => DB::table('posts')
                ->join('topics', 'posts.topicid', '=', 'topics.id')
                ->where('topics.userid', $userId)
                ->where('posts.userid', '!=', $userId)
                ->where('posts.id', '>', $replyCursor)
                ->count(),
        ];
    }

    /**
     * Comments on the user's own torrents newer than the cursor.
     *
     * @return list<array<string, mixed>>
     */
    public function newCommentsOnOwnTorrents(int $userId, int $lastId, int $limit = 20): array
    {
        return array_values(DB::table('comments')
            ->join('torrents', 'comments.torrent', '=', 'torrents.id')
            ->leftJoin('users', 'comments.user', '=', 'users.id')
            ->where('torrents.owner', $userId)
            ->where('comments.user', '!=', $userId)
            ->where('comments.id', '>', $lastId)
            ->orderBy('comments.id')
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
     * Posts in topics the user started, newer than the cursor.
     *
     * @return list<array<string, mixed>>
     */
    public function newPostsInOwnTopics(int $userId, int $lastId, int $limit = 20): array
    {
        return array_values(DB::table('posts')
            ->join('topics', 'posts.topicid', '=', 'topics.id')
            ->leftJoin('users', 'posts.userid', '=', 'users.id')
            ->where('topics.userid', $userId)
            ->where('posts.userid', '!=', $userId)
            ->where('posts.id', '>', $lastId)
            ->orderBy('posts.id')
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
