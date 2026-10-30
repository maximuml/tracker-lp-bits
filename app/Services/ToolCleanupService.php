<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PeerRepository;
use App\Repositories\ToolCleanupRepository;
use App\Repositories\TorrentRepository;
use App\Support\Database;
use App\Support\Logger;
use Illuminate\Support\Facades\Schema;

class ToolCleanupService
{
    public function __construct(
        private readonly ToolCleanupRepository $repository,
        private readonly PeerRepository $peerRepository,
        private readonly TorrentRepository $torrentRepository,
    ) {}

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
            $snatchRes = $this->repository->listDuplicateSnatchGroups($size, $idsField);
            if ($snatchRes->isEmpty()) {
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
                $this->repository->deleteSnatchedByIds($allDeleteIds);
            }
            if (! empty($pairUpdates)) {
                if ($claimTableExists) {
                    $this->repository->updateSnatchedIdReferences($claimTable, $pairUpdates);
                }
                if ($hitAndRunTableExists) {
                    $this->repository->updateSnatchedIdReferences($hitAndRunTable, $pairUpdates);
                }
                if ($stickyPromotionExists) {
                    $this->repository->updateSnatchedIdReferences($stickyPromotionParticipatorsTable, $pairUpdates);
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
            $results = $this->repository->listDuplicatePeerGroups($size, $idsField);
            if ($results->isEmpty()) {
                Logger::writeWithContext((string) '[DELETE_DUPLICATED_PEERS], no data', (string) 'info', (bool) false);
                break;
            }
            Logger::writeWithContext((string) ('[DELETE_DUPLICATED_PEERS], count: '.count($results)), (string) 'info', (bool) false);
            $allDeleteIds = [];
            $affectedTorrentIds = [];
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
                    $affectedTorrentIds[] = (int) $torrentId;
                }
            }
            if (! empty($allDeleteIds)) {
                $this->repository->deletePeersByIds($allDeleteIds);
                $this->resyncPeerCounters(array_values(array_unique($affectedTorrentIds)));
            }
        }
    }

    /**
     * Recount denormalized torrents.seeders/leechers after peer deletions —
     * removed duplicates never announce again, so the columns would drift.
     *
     * @param  array<int, int>  $torrentIds
     */
    private function resyncPeerCounters(array $torrentIds): void
    {
        if ($torrentIds === []) {
            return;
        }
        $counts = [];
        foreach ($this->peerRepository->countByTorrentSeeder($torrentIds) as $row) {
            $counts[(int) $row['torrent']][(int) $row['seeder'] === 1 ? 'seeders' : 'leechers'] = (int) $row['c'];
        }
        $this->torrentRepository->resyncPeerCounters($torrentIds, $counts);
    }
}
