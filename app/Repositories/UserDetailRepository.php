<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserModifyLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserDetailRepository
{
    /**
     * @return ?array<string, mixed>
     */
    public static function getUser(int $id): ?array
    {
        $user = User::query()->find($id);

        return $user === null ? null : $user->toArray();
    }

    public static function isFriend(int $userId, int $friendId): bool
    {
        return DB::table('friends')
            ->where('userid', $userId)
            ->where('friendid', $friendId)
            ->exists();
    }

    public static function isBlocked(int $userId, int $blockId): bool
    {
        return DB::table('blocks')
            ->where('userid', $userId)
            ->where('blockid', $blockId)
            ->exists();
    }

    public static function getIplogCount(int $userId): int
    {
        return DB::table('iplog')
            ->where('userid', $userId)
            ->distinct('ip')
            ->count('ip');
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getPeers(int $userId): array
    {
        return DB::table('peers')
            ->where('userid', $userId)
            ->selectRaw('min(peer_id) as peer_id, agent, ipv4, ipv6, port')
            ->groupBy('agent', 'ipv4', 'ipv6', 'port')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getTrueTraffic(int $userId): array
    {
        $row = DB::table('snatched')
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
     * @return ?array<int|string, mixed>
     */
    public static function getWarnedBy(int $warnedBy): ?array
    {
        $user = User::query()->where('id', $warnedBy)->select(['id', 'username'])->first();

        return $user === null ? null : ['id' => (int) $user->id, 'username' => (string) $user->username];
    }

    public static function getUserWithMedals(int $id): ?User
    {
        return User::query()->with('valid_medals')->find($id);
    }

    public static function getCommentCount(int $userId): int
    {
        return Comment::query()->where('user', $userId)->count();
    }

    public static function getPostCount(int $userId): int
    {
        return Post::query()->where('userid', $userId)->count();
    }

    public static function getTemporaryInviteCount(User $user): int
    {
        return $user->temporary_invites()->count();
    }

    public static function getModComment(int $userId): string
    {
        return UserModifyLog::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at instanceof Carbon ? $item->created_at->format('Y-m-d') : '', $item->content))
            ->implode("\n");
    }

    public static function getBonusComment(int $userId): string
    {
        return BonusLogs::query()
            ->where('uid', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at->format('Y-m-d'), $item->comment))
            ->implode("\n");
    }
}
