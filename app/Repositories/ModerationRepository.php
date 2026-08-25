<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class ModerationRepository extends BaseRepository
{
    public function reportExists(int $addedBy, int $reportId, string $type): bool
    {
        return DB::table('reports')
            ->where('addedby', $addedBy)
            ->where('reportid', $reportId)
            ->where('type', $type)
            ->exists();
    }

    /** @param  array<string, mixed>  $data */
    public function createReport(array $data): void
    {
        DB::table('reports')->insert($data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getForumPost(int $postId): ?array
    {
        $row = (array) DB::table('topics')
            ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
            ->where('posts.id', $postId)
            ->first(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid']);

        return empty($row) ? null : $row;
    }

    public function countReports(): int
    {
        return (int) DB::table('reports')->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReports(int $offset, int $limit): array
    {
        return DB::table('reports')
            ->orderBy('dealtwith')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function deleteBan(int $id): void
    {
        DB::table('bans')->where('id', $id)->delete();
    }

    /** @param  array<string, mixed>  $data */
    public function createBan(array $data): void
    {
        DB::table('bans')->insert($data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBans(): array
    {
        return DB::table('bans')
            ->orderByDesc('added')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMatchingBans(int $nip): array
    {
        return DB::table('bans')
            ->where('first', '<=', $nip)
            ->where('last', '>=', $nip)
            ->get(['first', 'last', 'comment'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function countIplogDistinct(int $userId): int
    {
        return (int) DB::table('iplog')->where('userid', $userId)->distinct('access')->count('access');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIphistoryRows(int $userId, int $offset, int $limit): array
    {
        $userHistory = DB::table('users as u')
            ->select('u.id', 'u.ip as ip', 'last_access as access')
            ->where('u.id', $userId);

        $ipLogHistory = DB::table('iplog')
            ->select('iplog.userid as id', 'iplog.ip as ip', 'iplog.access as access')
            ->where('iplog.userid', $userId);

        return $userHistory->union($ipLogHistory)
            ->orderBy('access', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array<int>
     */
    public function getUserIdsByIp(string $ip): array
    {
        return DB::table('users')->where('ip', $ip)->pluck('id')->all();
    }

    /**
     * @return array<int>
     */
    public function getIplogUserIdsByIp(string $ip): array
    {
        return DB::table('iplog')->where('ip', $ip)->pluck('userid')->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDuplicateIps(): array
    {
        return DB::table('users')
            ->selectRaw('ip, count(*) AS dupl')
            ->where('enabled', 'yes')
            ->where('ip', '!=', '')
            ->where('ip', '!=', '127.0.0.0')
            ->groupBy('ip')
            ->orderByDesc('dupl')
            ->orderBy('ip')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function getPeerCountsByIp(string $ip): array
    {
        $counts = [];
        foreach (DB::table('peers')->where('ip', $ip)->pluck('userid') as $uid) {
            $uid = (int) $uid;
            $counts[$uid] = ($counts[$uid] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIpsearchRows(string $ip, string $mask, bool $singleIp, string $order, int $offset, int $limit): array
    {
        $columns = ['u.id', 'u.username', 'u.ip as ip', 'u.ip as last_ip', 'u.last_access', 'u.last_access as access', 'u.email', 'u.invited_by', 'u.added', 'u.class', 'u.uploaded', 'u.downloaded', 'u.donor', 'u.enabled', 'u.warned'];

        $userQuery = DB::table('users as u')->select($columns);
        if ($singleIp) {
            $userQuery->where('u.ip', $ip);
        } else {
            $userQuery->whereRaw('INET_ATON(u.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
        }

        $iplogQuery = DB::table('users as u')
            ->rightJoin('iplog', 'u.id', '=', 'iplog.userid')
            ->select($columns);
        if ($singleIp) {
            $iplogQuery->where('iplog.ip', $ip);
        } else {
            $iplogQuery->whereRaw('INET_ATON(iplog.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
        }
        $iplogQuery->groupBy('u.id');

        $union = $userQuery->union($iplogQuery);
        $unionSql = $union->toSql();

        $orderby = match ($order) {
            'added' => 'added DESC',
            'username' => 'UPPER(username) ASC',
            'email' => 'email ASC',
            'last_ip' => 'last_ip ASC',
            'last_access' => 'last_ip ASC',
            default => 'access DESC',
        };

        return DB::table(DB::raw("({$unionSql}) as ipsearch"))
            ->mergeBindings($union)
            ->select('*')
            ->groupBy('id')
            ->orderByRaw($orderby)
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function countIpsearch(string $ip, string $mask, bool $singleIp): int
    {
        $columns = ['u.id', 'u.username', 'u.ip as ip', 'u.ip as last_ip', 'u.last_access', 'u.last_access as access', 'u.email', 'u.invited_by', 'u.added', 'u.class', 'u.uploaded', 'u.downloaded', 'u.donor', 'u.enabled', 'u.warned'];

        $userQuery = DB::table('users as u')->select($columns);
        if ($singleIp) {
            $userQuery->where('u.ip', $ip);
        } else {
            $userQuery->whereRaw('INET_ATON(u.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
        }

        $iplogQuery = DB::table('users as u')
            ->rightJoin('iplog', 'u.id', '=', 'iplog.userid')
            ->select($columns);
        if ($singleIp) {
            $iplogQuery->where('iplog.ip', $ip);
        } else {
            $iplogQuery->whereRaw('INET_ATON(iplog.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
        }
        $iplogQuery->groupBy('u.id');

        $union = $userQuery->union($iplogQuery);
        $unionSql = $union->toSql();

        $countRow = (array) DB::table(DB::raw("({$unionSql}) as ipsearch"))
            ->mergeBindings($union)
            ->selectRaw('count(DISTINCT id) as c')
            ->first();

        return (int) ($countRow['c'] ?? 0);
    }

    public function countIplogDistinctByUser(int $userId): int
    {
        return (int) DB::table('iplog')->where('userid', $userId)->distinct('ip')->count('ip');
    }
}
