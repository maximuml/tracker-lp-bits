<?php

namespace App\Support;

use App\Repositories\MessageRepository;
use App\Repositories\ShoutboxRepository;

/**
 * Fetch lightweight real-time toast notifications for the current user.
 *
 * Currently supports:
 * - unread private messages (PMs)
 * - shoutbox @mentions
 */
final class ToastNotifications
{
    private const LIMIT_PM = 10;

    private const MAX_BODY_LENGTH = 120;

    /**
     * @return array{cursors: array{last_pm_id: int, last_shout_id: int}, notifications: list<array<string, mixed>>}
     */
    public static function get(int $userId, int $lastPmId, int $lastShoutId, bool $init = false): array
    {
        $cursors = self::cursors($userId);

        if ($init) {
            return ['cursors' => $cursors, 'notifications' => []];
        }

        $notifications = MessageRepository::getUnreadPmNotifications($userId, $lastPmId, self::LIMIT_PM);
        foreach (ShoutboxRepository::getMentions($userId, $lastShoutId) as $mention) {
            $notifications[] = [
                'id' => 'shout_'.$mention['id'],
                'type' => 'shoutbox-mention',
                'title' => 'Shoutbox mention',
                'body' => self::truncate((string) $mention['text']),
                'from' => (string) ($mention['author_name'] ?? 'System'),
                'url' => 'shoutbox_history.php',
                'timestamp' => (int) $mention['date'],
            ];
        }

        usort($notifications, static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return ['cursors' => $cursors, 'notifications' => array_slice($notifications, 0, self::LIMIT_PM)];
    }

    /**
     * @return array{last_pm_id: int, last_shout_id: int}
     */
    private static function cursors(int $userId): array
    {
        return [
            'last_pm_id' => MessageRepository::getLastPmId($userId),
            'last_shout_id' => ShoutboxRepository::getLastShoutId(),
        ];
    }

    private static function truncate(string $text, int $length = self::MAX_BODY_LENGTH): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1).'…';
    }
}
