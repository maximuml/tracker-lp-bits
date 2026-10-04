<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserDetailRepository
{
    /**
     * @return ?array<string, mixed>
     */
    public function getUser(int $id): ?array
    {
        $user = User::query()->find($id);

        return $user === null ? null : $user->toApiArray();
    }

    public function isFriend(int $userId, int $friendId): bool
    {
        return DB::table('friends')
            ->where('userid', $userId)
            ->where('friendid', $friendId)
            ->exists();
    }

    public function isBlocked(int $userId, int $blockId): bool
    {
        return DB::table('blocks')
            ->where('userid', $userId)
            ->where('blockid', $blockId)
            ->exists();
    }

    public function getIplogCount(int $userId): int
    {
        return DB::table('iplog')
            ->where('userid', $userId)
            ->distinct('ip')
            ->count('ip');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getPeers(int $userId): array
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
    public function getTrueTraffic(int $userId): array
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
    public function getWarnedBy(int $warnedBy): ?array
    {
        $user = User::query()->where('id', $warnedBy)->select(['id', 'username'])->first();

        return $user === null ? null : ['id' => (int) $user->id, 'username' => (string) $user->username];
    }

    public function getCommentCount(int $userId): int
    {
        return Comment::query()->where('user', $userId)->count();
    }

    public function getPostCount(int $userId): int
    {
        return Post::query()->where('userid', $userId)->count();
    }

    public function getTemporaryInviteCount(User $user): int
    {
        return $user->temporary_invites()->count();
    }

    public function getModComment(int $userId): string
    {
        return UserModifyLog::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at instanceof Carbon ? $item->created_at->format('Y-m-d') : '', $item->content))
            ->implode("\n");
    }

    public function getBonusComment(int $userId): string
    {
        return BonusLogs::query()
            ->where('uid', $userId)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($item) => sprintf('[%s] %s', $item->created_at->format('Y-m-d'), $item->comment))
            ->implode("\n");
    }

    /**
     * @param  list<string>  $columns
     */
    public function findOrFailById(int $id, array $columns = ['*']): User
    {
        return User::query()->findOrFail($id, $columns);
    }

    public function findProfileWithCounts(int $id): User
    {
        return User::query()->withCount([
            'comments', 'posts', 'seeding_torrents', 'leeching_torrents',
            'torrents' => function ($query) {
                $query->whereHas('snatches');
            },
            'completed_torrents' => function ($query) use ($id) {
                $query->where('torrents.owner', '!=', $id);
            },
            'incomplete_torrents' => function ($query) use ($id) {
                $query->where('torrents.owner', '!=', $id);
            },
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertUsernameChangeLog(array $data): void
    {
        UsernameChangeLog::query()->create($data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertUserModifyLogs(array $rows): void
    {
        UserModifyLog::query()->insert($rows);
    }

    /**
     * Bulk-increment a numeric column across users (e.g. staff bulk
     * bonus/uploaded grants).
     *
     * @param  array<int, int>  $ids
     */
    public function incrementFieldForIds(array $ids, string $field, int $amount): int
    {
        return User::query()->whereIn('id', $ids)->increment($field, $amount);
    }

    /**
     * @param  list<string>  $columns
     */
    public function findByUsername(string $username, array $columns = ['*']): ?User
    {
        return User::query()->where('username', $username)->first($columns);
    }

    /**
     * @param  list<string>  $columns
     */
    public function findByEmail(string $email, array $columns = ['*']): ?User
    {
        return User::query()->where('email', $email)->first($columns);
    }

    /**
     * Case-sensitive email lookup (BINARY) — recovery/signup duplicate checks.
     */
    public function findByEmailBinary(string $email): ?User
    {
        return User::query()->whereRaw('BINARY email = ?', [$email])->first();
    }
}
