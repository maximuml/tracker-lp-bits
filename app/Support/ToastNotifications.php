<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use Nexus\Database\NexusDB;

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

    private const LIMIT_SHOUT = 50;

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

        $notifications = [];
        self::appendPmNotifications($userId, $lastPmId, $notifications);
        self::appendShoutboxMentions($userId, $lastShoutId, $notifications);

        usort($notifications, static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        return ['cursors' => $cursors, 'notifications' => array_slice($notifications, 0, self::LIMIT_PM)];
    }

    /**
     * @return array{last_pm_id: int, last_shout_id: int}
     */
    private static function cursors(int $userId): array
    {
        $lastPmId = (int) (Message::query()->where('receiver', $userId)->max('id') ?? 0);
        $lastShoutId = (int) (NexusDB::table('shoutbox')->max('id') ?? 0);

        return ['last_pm_id' => $lastPmId, 'last_shout_id' => $lastShoutId];
    }

    /**
     * @param list<array<string, mixed>> $notifications
     */
    private static function appendPmNotifications(int $userId, int $lastPmId, array &$notifications): void
    {
        $rows = Message::query()
            ->where('receiver', $userId)
            ->where('unread', 'yes')
            ->where('id', '>', $lastPmId)
            ->with('send_user')
            ->orderByDesc('id')
            ->limit(self::LIMIT_PM)
            ->get();

        foreach ($rows as $row) {
            $notifications[] = [
                'id' => 'pm_' . $row->id,
                'type' => 'pm',
                'title' => 'New message',
                'body' => self::truncate((string) $row->subject),
                'from' => (string) ($row->send_user->username ?? 'System'),
                'url' => 'messages.php?action=viewmessage&id=' . $row->id,
                'timestamp' => (int) strtotime((string) $row->added),
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $notifications
     */
    private static function appendShoutboxMentions(int $userId, int $lastShoutId, array &$notifications): void
    {
        $user = User::query()->find($userId, ['username']);
        if (!$user || $user->username === null || $user->username === '') {
            return;
        }

        $username = (string) $user->username;
        $pattern = '/(?<![\w\-\[\]\(\)])@' . preg_quote($username, '/') . '(?![\w\-\[\]\(\)])/ui';
        $like = '%@' . strtolower($username) . '%';

        $rows = NexusDB::table('shoutbox')
            ->leftJoin('users', 'shoutbox.userid', '=', 'users.id')
            ->where('shoutbox.id', '>', $lastShoutId)
            ->where('shoutbox.userid', '!=', $userId)
            ->whereRaw('LOWER(shoutbox.text) LIKE ?', [$like])
            ->select('shoutbox.id', 'shoutbox.date', 'shoutbox.text', 'users.username as author_name')
            ->orderBy('shoutbox.id')
            ->limit(self::LIMIT_SHOUT)
            ->get();

        foreach ($rows as $row) {
            $text = (string) ($row->text ?? '');
            if (!preg_match($pattern, $text)) {
                continue;
            }

            $notifications[] = [
                'id' => 'shout_' . $row->id,
                'type' => 'shoutbox-mention',
                'title' => 'Shoutbox mention',
                'body' => self::truncate($text),
                'from' => (string) ($row->author_name ?? 'System'),
                'url' => 'shoutbox_history.php',
                'timestamp' => (int) $row->date,
            ];
        }
    }

    private static function truncate(string $text, int $length = self::MAX_BODY_LENGTH): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 1) . '…';
    }
}
