<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ToolCleanupService
{
    /** @return  mixed */
    public function removeDuplicateSnatch()
    {
        $size = 2000;
        $stickyPromotionParticipatorsTable = 'sticky_promotion_participators';
        $claimTable = 'claims';
        $hitAndRunTable = 'hit_and_runs';
        $stickyPromotionExists = Schema::hasTable($stickyPromotionParticipatorsTable);
        $claimTableExists = Schema::hasTable($claimTable);
        $hitAndRunTableExists = Schema::hasTable($hitAndRunTable);
        $idsField = Database::groupConcatField('id');
        while (true) {
            $snatchRes = DB::table('snatched')
                ->select('userid', 'torrentid', DB::raw("$idsField as ids")) // @phpstan-ignore argument.type
                ->groupBy('userid', 'torrentid')
                ->havingRaw('count(*) > 1')
                ->limit($size)
                ->get();
            if (empty($snatchRes)) {
                break;
            }
            Logger::writeWithContext((string) ('[DELETE_DUPLICATED_SNATCH], count: '.count($snatchRes)), (string) 'info', (bool) false);
            $allDeleteIds = [];
            $pairUpdates = [];
            foreach ($snatchRes as $snatchRow) {
                $snatchRow = (array) $snatchRow;
                $torrentId = $snatchRow['torrentid'];
                $userId = $snatchRow['userid'];
                $idArr = explode(',', $snatchRow['ids']);
                sort($idArr, SORT_NUMERIC);
                $remainId = array_pop($idArr);
                Logger::writeWithContext((string) ("[DELETE_DUPLICATED_SNATCH], torrent: {$torrentId}, user: {$userId}, snatchIdStr: ".implode(',', $idArr)), (string) 'info', (bool) false);
                if (! empty($idArr)) {
                    $allDeleteIds = array_merge($allDeleteIds, $idArr);
                }
                $pairUpdates[] = ['torrent_id' => (int) $torrentId, 'uid' => (int) $userId, 'snatched_id' => (int) $remainId];
            }
            if (! empty($allDeleteIds)) {
                DB::table('snatched')->whereIn('id', $allDeleteIds)->delete();
            }
            if (! empty($pairUpdates)) {
                $caseParts = [];
                $whereParts = [];
                foreach ($pairUpdates as $pair) {
                    $tid = $pair['torrent_id'];
                    $uid = $pair['uid'];
                    $rid = $pair['snatched_id'];
                    $caseParts[] = "WHEN torrent_id = {$tid} AND uid = {$uid} THEN {$rid}";
                    $whereParts[] = "(torrent_id = {$tid} AND uid = {$uid})";
                }
                $caseSql = 'CASE '.implode(' ', $caseParts).' END';
                $whereSql = implode(' OR ', $whereParts);
                $caseExpr = DB::raw($caseSql); // @phpstan-ignore argument.type
                if ($claimTableExists) {
                    DB::table($claimTable)->whereRaw($whereSql)->update(['snatched_id' => $caseExpr]); // @phpstan-ignore argument.type
                }
                if ($hitAndRunTableExists) {
                    DB::table($hitAndRunTable)->whereRaw($whereSql)->update(['snatched_id' => $caseExpr]); // @phpstan-ignore argument.type
                }
                if ($stickyPromotionExists) {
                    DB::table($stickyPromotionParticipatorsTable)->whereRaw($whereSql)->update(['snatched_id' => $caseExpr]); // @phpstan-ignore argument.type
                }
            }
        }
    }

    /** @return  mixed */
    public function removeDuplicatePeer()
    {
        $size = 2000;
        $idsField = Database::groupConcatField('id');
        while (true) {
            $results = DB::table('peers')
                ->select('torrent', 'userid', DB::raw("$idsField as ids")) // @phpstan-ignore argument.type
                ->groupBy('torrent', 'peer_id', 'userid')
                ->havingRaw('count(*) > 1')
                ->limit($size)
                ->get();
            if (empty($results)) {
                Logger::writeWithContext((string) '[DELETE_DUPLICATED_PEERS], no data', (string) 'info', (bool) false);
                break;
            }
            Logger::writeWithContext((string) ('[DELETE_DUPLICATED_PEERS], count: '.count($results)), (string) 'info', (bool) false);
            $allDeleteIds = [];
            foreach ($results as $row) {
                $row = (array) $row;
                $torrentId = $row['torrent'];
                $userId = $row['userid'];
                $idArr = explode(',', $row['ids']);
                sort($idArr, SORT_NUMERIC);
                $remainId = array_pop($idArr);
                Logger::writeWithContext((string) ("[DELETE_DUPLICATED_PEERS], torrent: {$torrentId}, user: {$userId}, snatchIdStr: ".implode(',', $idArr)), (string) 'info', (bool) false);
                if (! empty($idArr)) {
                    $allDeleteIds = array_merge($allDeleteIds, $idArr);
                }
            }
            if (! empty($allDeleteIds)) {
                DB::table('peers')->whereIn('id', $allDeleteIds)->delete();
            }
        }
    }
}
