<?php

namespace App\Support;

use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\TorrentRepository;
use Nexus\Database\NexusDB;

/**
 * Legacy torrent operations helpers extracted from `include/functions.php`.
 *
 * Backs `deletetorrent()` and `get_torrent_2_user_value()`.
 */
final class TorrentOps
{
    /**
     * Delete one or more torrents and related records.
     *
     * Mirrors `deletetorrent($id, $notify = false)`.
     *
     * @param  int|int[]  $id
     */
    public static function deleteTorrents($id, bool $notify = false): void
    {
        $idArr = is_array($id) ? $id : [$id];
        $idStr = implode(', ', $idArr ?: [0]);

        $torrentInfo = Torrent::query()
            ->whereIn('id', $idArr)
            ->get()
            ->keyBy('id');

        $torrentRep = new TorrentRepository();
        $torrentDir = get_setting('main.torrent_dir');

        NexusDB::statement("DELETE FROM torrents WHERE id in ($idStr)");
        NexusDB::statement("DELETE FROM torrent_extras WHERE torrent_id in ($idStr)");
        NexusDB::statement("DELETE FROM snatched WHERE torrentid in ($idStr) and not exists (select 1 from users where id = snatched.userid)");

        foreach (['peers', 'files', 'comments'] as $x) {
            NexusDB::statement("DELETE FROM $x WHERE torrent in ($idStr)");
        }

        NexusDB::statement("DELETE FROM hit_and_runs WHERE torrent_id in ($idStr)");

        foreach ($idArr as $_id) {
            if ($torrentInfo->has($_id)) {
                $torrentRep->delPiecesHashCache($torrentInfo->get($_id)->pieces_hash);
            }

            \do_log("delete torrent: $_id", 'error');
            @unlink(getFullDirectory("$torrentDir/$_id.torrent"));

            TorrentOperationLog::add([
                'torrent_id' => $_id,
                'uid' => \get_user_id(),
                'action_type' => TorrentOperationLog::ACTION_TYPE_DELETE,
                'comment' => '',
            ], $notify);

            \do_action('torrent_delete', $_id);
            \fire_event('torrent_deleted', $torrentInfo->get($_id));
        }

        $meiliSearchRep = new MeiliSearchRepository();
        $meiliSearchRep->deleteDocuments($idArr);
    }

    /**
     * Compute the "torrent to user" value from a snatched row.
     *
     * Mirrors `get_torrent_2_user_value()`.
     *
     * @param  array<string, mixed>  $userSnatched
     */
    public static function userValue(array $userSnatched): float
    {
        $torrent2UserValue = 1.0;

        $result = NexusDB::getInstance()->query('SELECT * FROM torrents WHERE id = ' . (int) ($userSnatched['torrentid'] ?? 0));
        $torrentArr = NexusDB::getInstance()->fetchAssoc($result);

        if ($torrentArr) {
            if ($torrentArr['owner'] == $userSnatched['userid']) {
                $torrent2UserValue *= 0.7;
                $torrent2UserValue += ($userSnatched['uploaded'] / $torrentArr['size']) - 1 > 0
                    ? 0.2 - exp(-(($userSnatched['uploaded'] / $torrentArr['size']) - 1))
                    : ($userSnatched['uploaded'] / $torrentArr['size']) - 1;
                $torrent2UserValue += min(0.1, (($userSnatched['seedtime'] / 37 * 60 * 60) * 0.1));
            } else {
                if ($userSnatched['finished'] == 'yes') {
                    $torrent2UserValue *= 0.5;
                    $torrent2UserValue += ($userSnatched['uploaded'] / $torrentArr['size']) - 1 > 0
                        ? 0.4 - exp(-(($userSnatched['uploaded'] / $torrentArr['size']) - 1))
                        : ($userSnatched['uploaded'] / $torrentArr['size']) - 1;
                    $torrent2UserValue += min(0.1, (($userSnatched['seedtime'] / 22 * 60 * 60) * 0.1));
                } else {
                    $torrent2UserValue *= 0.2;
                    $torrent2UserValue += min(0.05, (($userSnatched['leechtime'] / 24 * 60 * 60) * 0.1));
                }
            }
        } else {
            if ($userSnatched['finished'] == 'no' && $userSnatched['uploaded'] > 0 && $userSnatched['downloaded'] == 0) {
                $torrent2UserValue *= 0.55;
                $torrent2UserValue += min(0.05, (($userSnatched['leechtime'] / 31 * 60 * 60) * 0.1));
                $torrent2UserValue += min(0.1, (($userSnatched['seedtime'] / 31 * 60 * 60) * 0.1));
            } elseif ($userSnatched['downloaded'] > 0) {
                $torrent2UserValue *= 0.38;
                $torrent2UserValue *= min(0.22, 0.1 * $userSnatched['uploaded'] / $userSnatched['downloaded']);
                $torrent2UserValue += min(0.05, (($userSnatched['leechtime'] / 22 * 60 * 60) * 0.1));
                $torrent2UserValue += min(0.12, (($userSnatched['seedtime'] / 22 * 60 * 60) * 0.1));
            } else {
                $torrent2UserValue *= 0.0;
            }
        }

        return (float) $torrent2UserValue;
    }
}
