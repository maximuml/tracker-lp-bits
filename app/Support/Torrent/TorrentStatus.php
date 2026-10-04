<?php

declare(strict_types=1);

namespace App\Support\Torrent;

use App\Models\Peer;
use App\Models\Snatch;

class TorrentStatus
{
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
        $peerList = Peer::query()
            ->where('userid', $uid)
            ->whereIn('torrent', $torrentIdArr)
            ->pluck('to_go', 'torrent')
            ->toArray();
        // download progress, from snatched
        $snatchedList = [];
        $res = Snatch::query()
            ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
            ->select('snatched.to_go', 'snatched.torrentid', 'torrents.size')
            ->where('snatched.userid', $uid)
            ->whereIn('snatched.torrentid', $torrentIdArr)
            ->toBase()
            ->get();
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
