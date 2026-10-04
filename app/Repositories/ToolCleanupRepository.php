<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\ToolCleanupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance queries for {@see ToolCleanupService}: duplicate-row
 * detection and removal on snatched/peers plus cross-table snatched_id re-pointing.
 */
final class ToolCleanupRepository
{
    /**
     * Groups of duplicated snatch rows: one row per (userid, torrentid) pair
     * holding more than one snatch record, with all ids group-concatenated.
     *
     * @return Collection<int, \stdClass>
     */
    public function listDuplicateSnatchGroups(int $limit, string $idsExpression): Collection
    {
        return DB::table('snatched')
            ->select('userid', 'torrentid', DB::raw("$idsExpression as ids")) // @phpstan-ignore argument.type
            ->groupBy('userid', 'torrentid')
            ->havingRaw('count(*) > 1')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int|string, mixed>  $ids
     */
    public function deleteSnatchedByIds(array $ids): void
    {
        DB::table('snatched')->whereIn('id', $ids)->delete();
    }

    /**
     * Re-point snatched_id references on a related table (claims /
     * hit_and_runs / sticky promotion participators) to the surviving row.
     *
     * @param  array<int, array{torrent_id: int, uid: int, snatched_id: int}>  $pairs
     */
    public function updateSnatchedIdReferences(string $table, array $pairs): void
    {
        $caseParts = [];
        $whereParts = [];
        foreach ($pairs as $pair) {
            $caseParts[] = "WHEN torrent_id = {$pair['torrent_id']} AND uid = {$pair['uid']} THEN {$pair['snatched_id']}";
            $whereParts[] = "(torrent_id = {$pair['torrent_id']} AND uid = {$pair['uid']})";
        }

        DB::table($table)
            ->whereRaw(implode(' OR ', $whereParts)) // @phpstan-ignore argument.type
            ->update(['snatched_id' => DB::raw('CASE '.implode(' ', $caseParts).' END')]); // @phpstan-ignore argument.type
    }

    /**
     * Groups of duplicated peer rows: one row per (torrent, peer_id, userid)
     * holding more than one peer record, with all ids group-concatenated.
     *
     * @return Collection<int, \stdClass>
     */
    public function listDuplicatePeerGroups(int $limit, string $idsExpression): Collection
    {
        return DB::table('peers')
            ->select('torrent', 'userid', DB::raw("$idsExpression as ids")) // @phpstan-ignore argument.type
            ->groupBy('torrent', 'peer_id', 'userid')
            ->havingRaw('count(*) > 1')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int|string, mixed>  $ids
     */
    public function deletePeersByIds(array $ids): void
    {
        DB::table('peers')->whereIn('id', $ids)->delete();
    }
}
