<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UserListingRepository
{
    /** @return  array<int|string, mixed> */
    public static function getCountries(): array
    {
        return DB::table('countries')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @param  array<int|string, mixed>  $filters */
    public static function countUsers(array $filters): int
    {
        return self::buildUserQuery($filters)->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public static function listUsers(array $filters, int $offset, int $perPage): array
    {
        $query = self::buildUserQuery($filters)
            ->leftJoin('countries as c', 'u.country', '=', 'c.id')
            ->select('u.id', 'u.class', 'u.added', 'u.last_access')
            ->selectRaw("CASE WHEN u.country > 0 THEN CONCAT('<img src=\"pic/flag/', c.flagpic, '\" alt=\"', c.name, '\">') ELSE '---' END as country")
            ->orderBy('u.username')
            ->offset($offset)
            ->limit($perPage);

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    private static function buildUserQuery(array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');
        $class = $filters['class'] ?? '-';
        $country = (int) ($filters['country'] ?? 0);
        $letter = trim($filters['letter'] ?? '');

        $query = DB::table('users as u')->where('u.status', 'confirmed');

        if ($search !== '') {
            $query->where('u.username', 'like', "%{$search}%");
        } elseif ($letter !== '') {
            $query->where('u.username', 'like', "{$letter}%");
        }

        if ($class !== '-') {
            $query->where('u.class', $class);
        }

        if ($country > 0) {
            $query->where('u.country', $country);
        }

        return $query;
    }

    /**
     * @param  array<int>  $userIds
     * @param  array<int|string, string>  $ips
     * @return array<string, mixed>
     */
    public static function getSearchExtraStats(array $userIds, array $ips, int $minClassRead): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));

        if ($userIds === []) {
            return ['peers' => [], 'posts' => [], 'comments' => [], 'bannedIps' => []];
        }

        $peers = DB::table('peers')
            ->whereIn('userid', $userIds)
            ->selectRaw('userid, SUM(uploaded) AS pul, SUM(downloaded) AS pdl')
            ->groupBy('userid')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->userid => ['pul' => (float) ($row->pul ?? 0), 'pdl' => (float) ($row->pdl ?? 0)]])
            ->all();

        $posts = DB::table('posts as p')
            ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
            ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
            ->whereIn('p.userid', $userIds)
            ->where('f.minclassread', '<=', $minClassRead)
            ->selectRaw('p.userid, COUNT(DISTINCT p.id) AS count')
            ->groupBy('p.userid')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->userid => (int) $row->count])
            ->all();

        $comments = DB::table('comments')
            ->whereIn('user', $userIds)
            ->selectRaw('user, COUNT(id) AS count')
            ->groupBy('user')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->user => (int) $row->count])
            ->all();

        $bannedIps = [];
        $ipLongs = [];
        foreach ($ips as $ip) {
            $ip = (string) $ip;
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLongs[$ip] = (int) ip2long($ip);
            }
        }

        if ($ipLongs !== []) {
            $bans = DB::table('bans')->get(['first', 'last'])->all();
            foreach ($ipLongs as $ip => $nip) {
                foreach ($bans as $ban) {
                    $first = (int) $ban->first;
                    $last = (int) $ban->last;
                    if ($nip >= $first && $nip <= $last) {
                        $bannedIps[$ip] = true;
                        break;
                    }
                }
            }
        }

        return compact('peers', 'posts', 'comments', 'bannedIps');
    }
}
