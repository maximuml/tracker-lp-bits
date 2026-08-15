<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Attachment;
use Nexus\Database\NexusDB;

final class AttachmentRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findByDlkey(string $dlkey): ?array
    {
        $record = Attachment::query()->where('dlkey', $dlkey)->first();

        return $record ? $record->toArray() : null;
    }

    /**
     * @param  array<int, string>  $dlkeys
     * @return array<string, array<string, mixed>>
     */
    public static function findByDlkeys(array $dlkeys): array
    {
        if (empty($dlkeys)) {
            return [];
        }

        return Attachment::query()
            ->whereIn('dlkey', $dlkeys)
            ->get()
            ->keyBy('dlkey')
            ->map(fn ($record) => $record->toArray())
            ->all();
    }

    public static function countRecentForUser(int $userId): int
    {
        $now = date('Y-m-d H:i:s', time() - 86400);

        return (int) NexusDB::table('attachments')
            ->where('userid', $userId)
            ->where('added', '>', $now)
            ->count();
    }
}
