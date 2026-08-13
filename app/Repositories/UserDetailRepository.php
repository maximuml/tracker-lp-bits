<?php

namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserModifyLog;
use Nexus\Database\NexusDB;

class UserDetailRepository
{
    /**
     * @param  int  $id
     * @return  ?array<int|string, mixed>
     */
    public static function getUser(int $id): ?array
    {
        $user = User::query()->find($id);

        return $user === null ? null : $user->toArray();
    }

    /**
     * @param  int  $userId
     * @param  int  $friendId
     */
    public static function isFriend(int $userId, int $friendId): bool
    {
        return NexusDB::table('friends')
            ->where('userid', $userId)
            ->where('friendid', $friendId)
            ->exists();
    }

    /**
     * @param  int  $userId
     * @param  int  $blockId
     */
    public static function isBlocked(int $userId, int $blockId): bool
    {
        return NexusDB::table('blocks')
            ->where('userid', $userId)
            ->where('blockid', $blockId)
            ->exists();
    }

    /** @param  int  $userId */
    public static function getIplogCount(int $userId): int
    {
        return NexusDB::table('iplog')
            ->where('userid', $userId)
            ->distinct('ip')
            ->count('ip');
    }

    /**
     * @param  int  $userId
     * @return  array<int|string, mixed>
     */
    public static function getPeers(int $userId): array
    {
        return NexusDB::table('peers')
            ->where('userid', $userId)
            ->selectRaw('min(peer_id) as peer_id, agent, ipv4, ipv6, port')
            ->groupBy('agent', 'ipv4', 'ipv6', 'port')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  int  $userId
     * @return  array<int|string, mixed>
     */
    public static function getTrueTraffic(int $userId): array
    {
        $row = NexusDB::table('snatched')
            ->where('userid', $userId)
            ->selectRaw('COALESCE(SUM(uploaded), 0) as uploaded')
            ->selectRaw('COALESCE(SUM(downloaded), 0) as downloaded')
            ->first();

        if ($row === null) {
            return ['uploaded' => 0, 'downloaded' => 0];
        }

        return [
            'uploaded' => (int) $row->uploaded,
            'downloaded' => (int) $row->downloaded,
        ];
    }

    /**
     * @param  int  $warnedBy
     * @return  ?array<int|string, mixed>
     */
    public static function getWarnedBy(int $warnedBy): ?array
    {
        $user = User::query()->where('id', $warnedBy)->select(['id', 'username'])->first();

        return $user === null ? null : ['id' => (int) $user->id, 'username' => (string) $user->username];
    }

    /**
     * @param  int  $id
     * @return  ?User
     */
    public static function getUserWithMedals(int $id): ?User
    {
        return User::query()->with('valid_medals')->find($id);
    }

    /** @param  int  $userId */
    public static function getCommentCount(int $userId): int
    {
        return Comment::query()->where('user', $userId)->count();
    }

    /** @param  int  $userId */
    public static function getPostCount(int $userId): int
    {
        return Post::query()->where('userid', $userId)->count();
    }

    /** @param  User  $user */
    public static function getTemporaryInviteCount(User $user): int
    {
        return $user->temporary_invites()->count();
    }

    /** @param  int  $userId */
    public static function getModComment(int $userId): string
    {
        return UserModifyLog::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at->format('Y-m-d'), $item->content))
            ->implode("\n");
    }

    /** @param  int  $userId */
    public static function getBonusComment(int $userId): string
    {
        return BonusLogs::query()
            ->where('uid', $userId)
            ->whereNotIn('business_type', BonusLogs::$businessTypeSeeding)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at->format('Y-m-d'), $item->comment))
            ->implode("\n");
    }
}
