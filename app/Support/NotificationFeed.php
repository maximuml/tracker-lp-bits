<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\NotificationFeedRepositoryInterface;
use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\StaffMessageRepository;
use Illuminate\Support\Facades\DB;

/**
 * Unified notification feed: unread PMs, shoutbox @mentions, comments on the
 * user's own torrents, replies in the user's own forum topics and staff
 * messages (staffbox).
 *
 * Two cursor semantics:
 * - since(): "already delivered" cursors supplied by the caller (client
 *   localStorage) — drives SSE pushes and toasts. The returned cursors are
 *   the contiguous per-channel prefix of what was actually delivered, never
 *   the source max — a backlog larger than the page is picked up by the
 *   next poll instead of being skipped.
 * - unread(): "read" cursors persisted in notification_cursors — drives the
 *   header badge and the dropdown panel. Offsets paginate the merged feed;
 *   `watermark` is a snapshot the client echoes back to markRead so items
 *   arriving after the panel opened are not silently swallowed.
 */
final class NotificationFeed
{
    private const LIMIT = 20;

    private const MAX_BODY_LENGTH = 120;

    /** Deterministic tie-break rank per channel for the merged ordering. */
    private const CHANNEL_RANK = [
        'pm' => 0,
        'shoutbox-mention' => 1,
        'comment' => 2,
        'topic_reply' => 3,
        'staff' => 4,
    ];

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ShoutboxRepositoryInterface $shoutboxRepository,
        private readonly NotificationFeedRepositoryInterface $feedRepository,
        private readonly StaffMessageRepository $staffMessageRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Items newer than the caller-supplied cursors (toast/push semantics).
     *
     * Per-channel rows are fetched oldest-first so the delivered set is a
     * contiguous prefix — the returned cursor advances only to the last
     * delivered prefix id, never to the source max. Anything not delivered
     * stays deliverable on the next poll.
     *
     * @param  array<string, int>  $cursors  channel => last delivered id
     * @return array{cursors: array<string, int>, notifications: list<array<string, mixed>>, has_more: bool}
     */
    public function since(int $userId, array $cursors, bool $init = false): array
    {
        if ($init) {
            $maxes = $this->feedRepository->channelMaxes($userId);
            $maxes['staff'] = $this->staffMax($userId);

            return ['cursors' => $maxes, 'notifications' => [], 'has_more' => false];
        }

        $userClass = $this->userClass($userId);
        $byChannel = [
            'pm' => $this->pmItems($userId, (int) ($cursors['pm'] ?? 0), self::LIMIT, true),
            'shout' => $this->mentionItems($userId, (int) ($cursors['shout'] ?? 0), self::LIMIT, true),
            'comment' => $this->commentItems($userId, (int) ($cursors['comment'] ?? 0), self::LIMIT, true),
            'topic_reply' => $this->topicReplyItems($userId, (int) ($cursors['topic_reply'] ?? 0), self::LIMIT, true, $userClass),
            'staff' => $this->staffItems($userId, (int) ($cursors['staff'] ?? 0), self::LIMIT, true),
        ];

        $merged = [];
        foreach ($byChannel as $items) {
            foreach ($items as $item) {
                $merged[] = $item;
            }
        }
        $this->sortMerged($merged);
        $page = array_slice($merged, 0, self::LIMIT);

        $deliveredByChannel = [];
        foreach ($page as $item) {
            $deliveredByChannel[$item['_channel']][] = (int) $item['_seq'];
        }

        $newCursors = [];
        $hasMore = false;
        foreach ($byChannel as $channel => $items) {
            $delivered = array_flip($deliveredByChannel[$channel] ?? []);
            $cursor = (int) ($cursors[$channel] ?? 0);
            // Advance over the longest delivered prefix of the fetched
            // (already permission-filtered) id sequence.
            foreach ($items as $item) {
                $id = (int) $item['_seq'];
                if (! isset($delivered[$id])) {
                    break;
                }
                $cursor = $id;
            }
            $newCursors[$channel] = $cursor;
            if (count($items) >= self::LIMIT || count($items) !== count($deliveredByChannel[$channel] ?? [])) {
                $hasMore = true;
            }
        }

        return [
            'cursors' => $newCursors,
            'notifications' => $this->stripMeta($page),
            'has_more' => $hasMore,
        ];
    }

    /**
     * Cheap "anything deliverable?" probe for long-lived SSE loops: raw
     * source maxima without item queries or permission joins. A false
     * positive only costs one regular since() pass on the next tick; a
     * false negative is impossible because every channel item comes from
     * these source tables.
     *
     * @param  array<string, int>  $cursors
     */
    public function hasNewerThan(int $userId, array $cursors): bool
    {
        foreach ($this->feedRepository->sourceMaxima($userId) as $channel => $max) {
            if ($max > (int) ($cursors[$channel] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Server-side unread feed for the bell panel (read-cursor semantics).
     *
     * First call for a user with no cursor rows seeds them to the current
     * maxes — everything pre-existing counts as read, like toast init. A
     * saved all-zero cursor set is a real state (user had nothing to
     * read), not "uninitialized" — row existence, not the sum of values,
     * decides.
     *
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, cursors: array<string, int>, watermark: array<string, int>, has_more: bool}
     */
    public function unread(int $userId, int $offset = 0): array
    {
        $saved = $this->feedRepository->savedCursors($userId);
        if (! $this->feedRepository->hasCursors($userId)) {
            $saved = $this->feedRepository->channelMaxes($userId);
            $saved['staff'] = $this->staffMax($userId);
            $this->feedRepository->saveCursors($userId, $saved);
        }

        $userClass = $this->userClass($userId);
        $counts = $this->feedRepository->unreadCounts($userId, $saved, $userClass);
        $counts['shout'] = $this->shoutboxRepository->countMentions($userId, (int) $saved['shout']);
        $counts['staff'] = $this->staffMessageRepository
            ->buildStaffMessageQuery($userId)
            ->where('id', '>', (int) ($saved['staff'] ?? 0))
            ->count();
        $counts['total'] = array_sum($counts);

        // Newest-first per channel so the merged top-LIMIT is the true
        // global newest page; pagination widens the per-channel fetch
        // window rather than shifting a shared offset into each query.
        $fetchLimit = max(self::LIMIT, $offset + self::LIMIT);
        $items = $this->pmItems($userId, (int) $saved['pm'], $fetchLimit, false);
        foreach ([
            $this->mentionItems($userId, (int) $saved['shout'], $fetchLimit, false),
            $this->commentItems($userId, (int) $saved['comment'], $fetchLimit, false),
            $this->topicReplyItems($userId, (int) $saved['topic_reply'], $fetchLimit, false, $userClass),
            $this->staffItems($userId, (int) $saved['staff'], $fetchLimit, false),
        ] as $channelItems) {
            foreach ($channelItems as $item) {
                $items[] = $item;
            }
        }
        $this->sortMerged($items);
        $page = array_slice($items, $offset, self::LIMIT);

        $watermark = $this->feedRepository->channelMaxes($userId);
        $watermark['staff'] = $this->staffMax($userId);

        return [
            'items' => $this->stripMeta($page),
            'counts' => $counts,
            'cursors' => $saved,
            'watermark' => $watermark,
            // counts and the item queries are separate reads — a delete
            // between them can yield an empty page while the count still
            // claims more rows. An empty page always ends pagination so
            // the client cannot loop on a drifting count.
            'has_more' => $page !== [] && $counts['total'] > $offset + count($page),
        ];
    }

    /**
     * Mark the feed read up to a watermark. Without a snapshot the whole
     * current backlog is marked (legacy behaviour); with one, events that
     * arrived after the panel was opened stay unread. The snapshot is
     * clamped to current source maxes so a forged watermark cannot mark
     * future events read. Writes are monotonic — a late, stale request
     * never moves a cursor backwards.
     *
     * @param  array<string, int>|null  $watermark
     * @return array<string, int>
     */
    public function markAllRead(int $userId, ?array $watermark = null): array
    {
        $maxes = $this->feedRepository->channelMaxes($userId);
        $maxes['staff'] = $this->staffMax($userId);

        if ($watermark !== null) {
            foreach ($maxes as $channel => $max) {
                $maxes[$channel] = min($max, max(0, (int) ($watermark[$channel] ?? 0)));
            }
        }

        $this->feedRepository->saveCursors($userId, $maxes);

        return $maxes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pmItems(int $userId, int $lastPmId, int $limit, bool $oldestFirst): array
    {
        $items = [];
        foreach ($this->messageRepository->getUnreadPmNotifications($userId, $lastPmId, $limit, $oldestFirst) as $n) {
            $n['type'] = 'pm';
            $n['_channel'] = 'pm';
            $n['_seq'] = (int) substr((string) $n['id'], 3);
            $items[] = $n;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mentionItems(int $userId, int $lastShoutId, int $limit, bool $oldestFirst): array
    {
        $items = [];
        foreach ($this->shoutboxRepository->getMentions($userId, $lastShoutId, $limit, $oldestFirst) as $mention) {
            $items[] = [
                'id' => 'shout_'.$mention['id'],
                'type' => 'shoutbox-mention',
                'title' => (string) __('legacy/notifications.title_shoutbox_mention'),
                'body' => $this->truncate((string) $mention['text']),
                'from' => (string) ($mention['author_name'] ?? 'System'),
                'url' => 'shoutbox_history.php',
                'timestamp' => (int) $mention['date'],
                '_channel' => 'shout',
                '_seq' => (int) $mention['id'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commentItems(int $userId, int $lastCommentId, int $limit, bool $oldestFirst): array
    {
        $items = [];
        foreach ($this->feedRepository->newCommentsOnOwnTorrents($userId, $lastCommentId, $limit, $oldestFirst) as $row) {
            $items[] = [
                'id' => 'comment_'.$row['id'],
                'type' => 'comment',
                'title' => (string) __('legacy/notifications.title_comment'),
                'body' => $this->truncate((string) ($row['text'] ?? '')),
                'from' => (string) ($row['author_name'] ?? ''),
                'url' => 'details.php?id='.(int) $row['torrent'].'#comments',
                'context' => (string) ($row['torrent_name'] ?? ''),
                'timestamp' => (int) ($row['ts'] ?? 0),
                '_channel' => 'comment',
                '_seq' => (int) $row['id'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topicReplyItems(int $userId, int $lastReplyId, int $limit, bool $oldestFirst, int $userClass): array
    {
        $items = [];
        foreach ($this->feedRepository->newPostsInOwnTopics($userId, $lastReplyId, $limit, $oldestFirst, $userClass) as $row) {
            $items[] = [
                'id' => 'reply_'.$row['id'],
                'type' => 'topic_reply',
                'title' => (string) __('legacy/notifications.title_topic_reply'),
                'body' => $this->truncate((string) ($row['body'] ?? '')),
                'from' => (string) ($row['author_name'] ?? ''),
                'url' => 'forums.php?action=viewtopic&topicid='.(int) $row['topicid'].'&page=last',
                'context' => (string) ($row['topic_subject'] ?? ''),
                'timestamp' => (int) ($row['ts'] ?? 0),
                '_channel' => 'topic_reply',
                '_seq' => (int) $row['id'],
            ];
        }

        return $items;
    }

    private function staffMax(int $userId): int
    {
        return (int) $this->staffMessageRepository->buildStaffMessageQuery($userId)->max('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffItems(int $userId, int $lastId, int $limit, bool $oldestFirst): array
    {
        $items = [];
        $rows = $this->staffMessageRepository
            ->buildStaffMessageQuery($userId)
            ->where('staffmessages.id', '>', $lastId)
            ->leftJoin('users', 'staffmessages.sender', '=', 'users.id')
            ->orderBy('staffmessages.id', $oldestFirst ? 'asc' : 'desc')
            ->limit($limit)
            ->get([
                'staffmessages.id',
                'staffmessages.sender',
                'staffmessages.added',
                'staffmessages.subject',
                'staffmessages.msg',
                'users.username as sender_username',
                DB::raw('UNIX_TIMESTAMP(staffmessages.added) as ts'),
            ]);

        foreach ($rows as $row) {
            $items[] = [
                'id' => 'staff_'.$row->id,
                'type' => 'staff',
                'title' => (string) __('legacy/notifications.title_staff'),
                'body' => $this->truncate((string) $row->msg),
                'from' => (string) ($row->sender_username ?? 'System'),
                'url' => 'staffbox.php?action=viewanswer&msgid='.(int) $row->id,
                'timestamp' => (int) $row->getAttribute('ts'),
                '_channel' => 'staff',
                '_seq' => (int) $row->id,
            ];
        }

        return $items;
    }

    /**
     * Newest-first merge with a deterministic total order: timestamp desc,
     * then a fixed channel rank, then source id desc — equal timestamps
     * across channels cannot reorder between polls.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function sortMerged(array &$items): void
    {
        usort($items, static function (array $a, array $b): int {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0)
                ?: (self::CHANNEL_RANK[$b['type'] ?? ''] ?? 9) <=> (self::CHANNEL_RANK[$a['type'] ?? ''] ?? 9)
                ?: ((int) ($b['_seq'] ?? 0)) <=> ((int) ($a['_seq'] ?? 0));
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function stripMeta(array $items): array
    {
        foreach ($items as &$item) {
            unset($item['_channel'], $item['_seq']);
        }

        return $items;
    }

    private function userClass(int $userId): int
    {
        return (int) $this->userRepository->findById($userId, ['class'])?->class;
    }

    private function truncate(string $text, int $length = self::MAX_BODY_LENGTH): string
    {
        $text = trim(strip_tags((string) preg_replace('/\[\/?[a-zA-Z*]+(?:=[^\]]*)?\]/', '', $text)));
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1).'…';
    }
}
