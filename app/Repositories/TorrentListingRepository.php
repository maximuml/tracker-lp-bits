<?php

namespace App\Repositories;

use Carbon\Carbon;
use Nexus\Database\NexusDB;

class TorrentListingRepository
{
    /** @param  array<int|string, mixed>  $options */
    public static function getCount(array $options): int
    {
        $query = self::buildBaseQuery($options);

        return $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return  array<int|string, mixed>
     */
    public static function getList(array $options): array
    {
        $orderBy = preg_replace('/^ORDER BY /i', '', $options['order_by']);
        $query = self::buildBaseQuery($options)
            ->select($options['fields'])
            ->selectRaw('? as search_box_id', [$options['search_box_id']])
            ->orderByRaw($orderBy)
            ->offset($options['offset'])
            ->limit($options['limit']);

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return  \Illuminate\Database\Query\Builder
     */
    private static function buildBaseQuery(array $options): \Illuminate\Database\Query\Builder
    {
        $query = NexusDB::table('torrents');

        if (! empty($options['join_users'])) {
            $query = $query->leftJoin('users', 'torrents.owner', '=', 'users.id');
        }

        if (! empty($options['join_torrent_tags']) && ! empty($options['tag_id'])) {
            $query = $query->join('torrent_tags', function ($join) use ($options) {
                $join->on('torrents.id', '=', 'torrent_tags.torrent_id')
                    ->where('torrent_tags.tag_id', $options['tag_id']);
            });
        }

        if (! empty($options['join_torrent_extras'])) {
            $query = $query->join('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id');
        }

        $where = $options['where'] ?? '';
        $where = preg_replace('/^WHERE\s+/i', '', $where);
        if ($where !== '') {
            $query = $query->whereRaw($where);
        }

        return $query;
    }

    /**
     * @param  int  $secondsBack
     * @return  array<int|string, mixed>
     */
    public static function getHotSearch(int $secondsBack = 259200): array
    {
        return NexusDB::table('suggest')
            ->select('keywords')
            ->selectRaw('COUNT(DISTINCT userid) as count')
            ->where('adddate', '>', Carbon::createFromTimestamp(TIMENOW - $secondsBack)->format('Y-m-d H:i:s'))
            ->groupBy('keywords')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @param  int  $secondsBack */
    public static function cleanupSuggest(int $secondsBack = 518400): void
    {
        NexusDB::table('suggest')
            ->where('adddate', '<', Carbon::createFromTimestamp(TIMENOW - $secondsBack)->format('Y-m-d H:i:s'))
            ->delete();
    }
}
