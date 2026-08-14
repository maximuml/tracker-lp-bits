<?php

namespace App\Repositories;

use Nexus\Database\NexusDB;

class PageLayoutRepository extends BaseRepository
{
    public function getInboxCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('receiver', $userId)
            ->where('location', '<>', 0)
            ->count();
    }

    public function getOutboxCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('sender', $userId)
            ->where('saved', 'yes')
            ->count();
    }

    public function getConnectable(int $userId): string
    {
        return NexusDB::table('peers')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->value('connectable') ?? 'unknown';
    }

    public function getActiveSeedCount(int $userId): int
    {
        return (int) NexusDB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 'yes')
            ->count();
    }

    public function getActiveLeechCount(int $userId): int
    {
        return (int) NexusDB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 'no')
            ->count();
    }

    public function getUnreadMessageCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('receiver', $userId)
            ->where('unread', 'yes')
            ->count();
    }

    public function getUnreadNewsCount(?string $lastHome): int
    {
        $query = NexusDB::table('news')->where('notify', 'yes');
        if (!empty($lastHome) && $lastHome !== '0000-00-00 00:00:00') {
            $query->where('added', '>', $lastHome);
        }

        return (int) $query->count();
    }

    public function getTotalReports(): int
    {
        return (int) NexusDB::table('reports')->count();
    }

    public function getTotalCheaters(): int
    {
        return (int) NexusDB::table('cheaters')->count();
    }

    public function getTorrentApprovalNoneCount(): int
    {
        return (int) NexusDB::table('torrents')->where('approval_status', 0)->count();
    }

    public function getSeedBoxApprovalCount(): int
    {
        return (int) NexusDB::table('seed_box_records')->where('status', 0)->count();
    }

    public function getOpenComplaintsCount(): int
    {
        return (int) NexusDB::table('complains')->where('answered', 0)->count();
    }

    public function getOpenReportsCount(): int
    {
        return (int) NexusDB::table('reports')->where('dealtwith', 0)->count();
    }

    public function getOpenCheatersCount(): int
    {
        return (int) NexusDB::table('cheaters')->where('dealtwith', 0)->count();
    }

    /** @param  array<string, mixed>  $data */
    public function updateUser(int $userId, array $data): void
    {
        NexusDB::table('users')->where('id', $userId)->update($data);
    }
}
