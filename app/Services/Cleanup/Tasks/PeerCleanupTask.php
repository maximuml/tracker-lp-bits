<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Repositories\PeerRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Time;
use Carbon\Carbon;

/**
 * Priority Class 1: peer and seed-bonus cleanup.
 */
final class PeerCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly PeerRepository $peerRepository,
        private readonly UserCleanupRepository $userCleanupRepository,
        private readonly TorrentRepository $torrentRepository,
    ) {}

    /**
     * Priority Class 1: remove peers whose last_action is older than the dead
     * threshold, then resync the denormalized torrents.seeders/leechers
     * counters for the affected torrents — pruned peers never announce
     * again, so without this the columns would drift until the next
     * UpdateTorrentSeedersEtc batch.
     */
    public function prunePeers(): string
    {
        $deadtime = date('Y-m-d H:i:s', Time::deadThreshold(
            (int) SiteConfig::current()->main->anninterthree(3600),
            time()
        ));

        $torrentIds = $this->peerRepository->listTorrentIdsInactiveBefore($deadtime);
        $this->peerRepository->deleteInactiveBefore($deadtime);
        $this->resyncPeerCounters($torrentIds);

        return 'update peer status';
    }

    /** @param array<int, int> $torrentIds */
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

    /**
     * Priority Class 1: reset per-hour seed bonus counters for users whose seeding
     * snapshot is older than two autoclean intervals.
     */
    public function resetSeedBonusCounters(): string
    {
        $interval = (int) SiteConfig::current()->main->autocleanIntervalOne(900);
        $cutoff = Carbon::now()->subSeconds(2 * $interval)->toDateTimeString();

        $this->userCleanupRepository->resetSeedBonusCounters($cutoff);

        return 'reset seed bonus counters';
    }

    public function run(): string
    {
        return $this->prunePeers();
    }
}
