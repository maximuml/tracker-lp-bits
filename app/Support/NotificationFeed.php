<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\MessageRepository;
use App\Repositories\NotificationFeedRepository;
use App\Repositories\ShoutboxRepository;

/**
 * Unified notification feed: unread PMs, shoutbox @mentions, comments on the
 * user's own torrents and replies in the user's own forum topics.
 *
 * Two cursor semantics:
 * - since(): "already delivered" cursors supplied by the caller (client
 *   localStorage) — drives SSE pushes and toasts.
 * - unread(): "read" cursors persisted in notification_cursors — drives the
 *   header badge and the dropdown panel.
 */
final class NotificationFeed
{
    private const LIMIT = 20;

    private const MAX_BODY_LENGTH = 120;

    public function __construct(
        private readonly MessageRepository $messageRepository = new MessageRepository,
        private readonly ShoutboxRepository $shoutboxRepository = new ShoutboxRepository,
        private readonly NotificationFeedRepository $feedRepository = new NotificationFeedRepository,
    ) {}

    /**
     * Items newer than the caller-supplied cursors (toast/push semantics).
     *
     * @param  array<string, int>  $cursors  channel => last seen id
     * @return array{cursors: array<string, int>, notifications: list<array<string, mixed>>}
     */
    public function since(int $userId, array $cursors, bool $init = false): array
    {
        $maxes = $this->feedRepository->channelMaxes($userId);

        if ($init) {
            return ['cursors' => $maxes, 'notifications' => []];
        }

        $notifications = $this->pmItems($userId, (int) ($cursors['pm'] ?? 0));
        foreach ($this->mentionItems($userId, (int) ($cursors['shout'] ?? 0)) as $item) {
            $notifications[] = $item;
        }
        foreach ($this->commentItems($userId, (int) ($cursors['comment'] ?? 0)) as $item) {
            $notifications[] = $item;
        }
        foreach ($this->topicReplyItems($userId, (int) ($cursors['topic_reply'] ?? 0)) as $item) {
            $notifications[] = $item;
        }

        usort($notifications, static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return ['cursors' => $maxes, 'notifications' => array_slice($notifications, 0, self::LIMIT)];
    }

    /**
     * Server-side unread feed for the bell panel (read-cursor semantics).
     *
     * First call for a user with no saved cursors seeds them to the current
     * maxes — everything pre-existing counts as read, like toast init.
     *
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>, cursors: array<string, int>}
     */
    public function unread(int $userId): array
    {
        $saved = $this->feedRepository->savedCursors($userId);
        if (array_sum($saved) === 0) {
            $saved = $this->feedRepository->channelMaxes($userId);
            $this->feedRepository->saveCursors($userId, $saved);
        }

        $counts = $this->feedRepository->unreadCounts($userId, $saved);
        $items = $this->pmItems($userId, (int) $saved['pm']);
        foreach ($this->mentionItems($userId, (int) $saved['shout']) as $item) {
            $items[] = $item;
        }
        $counts['shout'] = count(array_filter($items, static fn (array $i): bool => ($i['type'] ?? '') === 'shoutbox-mention'));
        $counts['total'] = array_sum($counts);

        foreach ($this->commentItems($userId, (int) $saved['comment'], self::LIMIT) as $item) {
            $items[] = $item;
        }
        foreach ($this->topicReplyItems($userId, (int) $saved['topic_reply'], self::LIMIT) as $item) {
            $items[] = $item;
        }
        usort($items, static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return [
            'items' => array_slice($items, 0, self::LIMIT),
            'counts' => $counts,
            'cursors' => $saved,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function markAllRead(int $userId): array
    {
        $maxes = $this->feedRepository->channelMaxes($userId);
        $this->feedRepository->saveCursors($userId, $maxes);

        return ['pm' => 0, 'comment' => 0, 'topic_reply' => 0, 'total' => 0, 'shout' => 0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pmItems(int $userId, int $lastPmId): array
    {
        $items = [];
        foreach ($this->messageRepository->getUnreadPmNotifications($userId, $lastPmId, self::LIMIT) as $n) {
            $n['type'] = 'pm';
            $items[] = $n;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mentionItems(int $userId, int $lastShoutId): array
    {
        $items = [];
        foreach ($this->shoutboxRepository->getMentions($userId, $lastShoutId) as $mention) {
            $items[] = [
                'id' => 'shout_'.$mention['id'],
                'type' => 'shoutbox-mention',
                'title' => 'Shoutbox mention',
                'body' => $this->truncate((string) $mention['text']),
                'from' => (string) ($mention['author_name'] ?? 'System'),
                'url' => 'shoutbox_history.php',
                'timestamp' => (int) $mention['date'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commentItems(int $userId, int $lastCommentId, int $limit = self::LIMIT): array
    {
        $items = [];
        foreach ($this->feedRepository->newCommentsOnOwnTorrents($userId, $lastCommentId, $limit) as $row) {
            $items[] = [
                'id' => 'comment_'.$row['id'],
                'type' => 'comment',
                'title' => (string) __('legacy/notifications.title_comment'),
                'body' => $this->truncate((string) ($row['text'] ?? '')),
                'from' => (string) ($row['author_name'] ?? ''),
                'url' => 'details.php?id='.(int) $row['torrent'].'#comments',
                'context' => (string) ($row['torrent_name'] ?? ''),
                'timestamp' => (int) ($row['ts'] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topicReplyItems(int $userId, int $lastReplyId, int $limit = self::LIMIT): array
    {
        $items = [];
        foreach ($this->feedRepository->newPostsInOwnTopics($userId, $lastReplyId, $limit) as $row) {
            $items[] = [
                'id' => 'reply_'.$row['id'],
                'type' => 'topic_reply',
                'title' => (string) __('legacy/notifications.title_topic_reply'),
                'body' => $this->truncate((string) ($row['body'] ?? '')),
                'from' => (string) ($row['author_name'] ?? ''),
                'url' => 'viewtopic.php?topicid='.(int) $row['topicid'].'&page=last',
                'context' => (string) ($row['topic_subject'] ?? ''),
                'timestamp' => (int) ($row['ts'] ?? 0),
            ];
        }

        return $items;
    }

    private function truncate(string $text, int $length = self::MAX_BODY_LENGTH): string
    {
        $text = trim(strip_tags($text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1).'…';
    }
}
