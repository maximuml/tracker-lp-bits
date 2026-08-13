<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

final class AdminStatsRepository
{
    /**
     * @return array<string, mixed>
     */
    public static function stats(string $uporder, string $catorder): array
    {
        $nTor = NexusDB::table('torrents')->count();
        $nPeers = NexusDB::table('peers')->count();

        return [
            'n_tor' => $nTor,
            'n_peers' => $nPeers,
            'upers' => self::uploaderActivity($uporder, $nTor, $nPeers),
            'cats' => $nTor > 0 ? self::categoryActivity($catorder) : collect(),
            'uporder' => $uporder,
            'catorder' => $catorder,
        ];
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private static function uploaderActivity(string $uporder, int $nTor, int $nPeers): Collection
    {
        $orderBy = match ($uporder) {
            'lastul' => 'last DESC, name',
            'torrents' => 'n_t DESC, name',
            'peers' => 'n_p DESC, name',
            default => 'name',
        };

        $base = NexusDB::table('users as u')
            ->selectRaw('u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
            ->leftJoin('torrents as t', 'u.id', '=', 't.owner')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent');

        $first = clone $base;
        $first->where('u.class', 3)->groupBy('u.id');

        $second = clone $base;
        $second->where('u.class', '>', 3)->groupBy('u.id');

        return $first->union($second)->orderByRaw($orderBy)->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private static function categoryActivity(string $catorder): Collection
    {
        $orderBy = match ($catorder) {
            'lastul' => 'last DESC, c.name',
            'torrents' => 'n_t DESC, c.name',
            'peers' => 'n_p DESC, c.name',
            default => 'c.name',
        };

        return NexusDB::table('categories as c')
            ->selectRaw('c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
            ->leftJoin('torrents as t', 't.category', '=', 'c.id')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
            ->groupBy('c.id')
            ->orderByRaw($orderBy)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function allagents(): Collection
    {
        return NexusDB::table('peers')
            ->selectRaw('agent, count(*) as counts')
            ->groupBy('agent')
            ->orderBy('agent')
            ->get();
    }
}
