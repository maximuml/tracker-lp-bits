<?php

declare(strict_types=1);

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TorrentListingRepository
{
    /** @param  array<int|string, mixed>  $options */
    public function getCount(array $options): int
    {
        $query = $this->buildBaseQuery($options);

        return $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return array<int|string, mixed>
     */
    public function getList(array $options): array
    {
        $query = $this->buildBaseQuery($options)
            ->select($options['fields'])
            ->selectRaw('? as search_box_id', [$options['search_box_id']])
            ->offset($options['offset'])
            ->limit($options['limit']);

        $orderBy = $options['order_by'] ?? [];
        foreach ($orderBy as $order) {
            [$column, $direction] = is_array($order) ? array_pad($order, 2, 'asc') : [$order, 'asc'];
            $direction = match (is_string($direction) ? strtolower($direction) : '') {
                'desc' => 'desc',
                default => 'asc',
            };
            $query->orderBy((string) $column, $direction);
        }

        return $query->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $options
     */
    private function buildBaseQuery(array $options): Builder
    {
        $query = DB::table('torrents');

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
            $whereBindings = $options['where_bindings'] ?? [];
            $query = $query->whereRaw($where, $whereBindings); // @phpstan-ignore argument.type
        }

        return $query;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getHotSearch(int $secondsBack = 259200): array
    {
        return DB::table('suggest')
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

    public function cleanupSuggest(int $secondsBack = 518400): void
    {
        DB::table('suggest')
            ->where('adddate', '<', Carbon::createFromTimestamp(TIMENOW - $secondsBack)->format('Y-m-d H:i:s'))
            ->delete();
    }
}
