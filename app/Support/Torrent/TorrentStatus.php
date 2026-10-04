<?php

declare(strict_types=1);

namespace App\Support\Torrent;

use App\Repositories\PeerRepository;
use App\Repositories\SnatchRepository;

class TorrentStatus
{
    public function __construct(
        private readonly PeerRepository $peerRepository,
        private readonly SnatchRepository $snatchRepository,
    ) {}

    /**
     * get torrent seeding or leeching status, download progress of someone
     *
     * @param  array<int, int>  $torrentIdArr
     * @return array<int, array{finished: int, progress: float, active_status: string}>
     */
    public function listLeechingSeedingStatus(int $uid, array $torrentIdArr): array
    {
        if (empty($torrentIdArr)) {
            return [];
        }
        // seeding or leeching, from peers
        $peerList = $this->peerRepository->pluckToGoByUserTorrents($uid, $torrentIdArr);
        // download progress, from snatched
        $snatchedList = [];
        $res = $this->snatchRepository->listToGoWithSize($uid, $torrentIdArr);
        foreach ($res as $row) {
            $row = (array) $row;
            $id = $row['torrentid'];
            $activeStatus = 'inactivity';
            if (isset($peerList[$id])) {
                if ($peerList[$id] == 0) {
                    $activeStatus = 'seeding';
                } else {
                    $activeStatus = 'leeching';
                }
            }
            $torrentSize = (float) $row['size'];
            if ($torrentSize <= 0) {
                $progress = '100.0000';
            } else {
                $realDownloaded = $torrentSize - (float) $row['to_go'];
                $progress = sprintf('%.4f', $realDownloaded / $torrentSize);
            }
            $snatchedList[$id] = [
                'finished' => $row['to_go'] == 0 ? 1 : 0,
                'progress' => floatval($progress),
                'active_status' => $activeStatus,
            ];
        }

        return $snatchedList;
    }
}
