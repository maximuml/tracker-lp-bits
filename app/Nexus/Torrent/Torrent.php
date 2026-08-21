<?php

namespace Nexus\Torrent;

use Nexus\Database\DatabaseException;
use Nexus\Database\NexusDB;

class Torrent
{
    /**
     * get torrent seeding or leeching status, download progress of someone
     *
     * @return array
     *
     * @throws DatabaseException
     */
    public function listLeechingSeedingStatus(int $uid, array $torrentIdArr)
    {
        if (empty($torrentIdArr)) {
            return [];
        }
        // seeding or leeching, from peers
        $peerList = NexusDB::table('peers')
            ->where('userid', $uid)
            ->whereIn('torrent', $torrentIdArr)
            ->pluck('to_go', 'torrent')
            ->toArray();
        // download progress, from snatched
        $snatchedList = [];
        $res = NexusDB::table('snatched')
            ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
            ->select('snatched.to_go', 'snatched.torrentid', 'torrents.size')
            ->where('snatched.userid', $uid)
            ->whereIn('snatched.torrentid', $torrentIdArr)
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
                'finished' => $row['to_go'] == 0 ? 'yes' : 'no',
                'progress' => floatval($progress),
                'active_status' => $activeStatus,
            ];
        }

        return $snatchedList;
    }

    public function renderProgressBar($activeStatus, $progress): string
    {
        $color = '#aaa';
        if ($activeStatus == 'seeding') {
            $color = 'green';
        } elseif ($activeStatus == 'leeching') {
            $color = 'blue';
        }
        $progress = ($progress * 100).'%';
        $result = sprintf(
            '<div style="padding: 1px;margin-top: 2px;border: 1px solid #838383" title="%s"><div style="width: %s;background-color: %s;height: 2px"></div></div>',
            $activeStatus." $progress", $progress, $color
        );

        return $result;
    }
}
