<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PeerSeeder;
use App\Enums\SnatchFinished;
use App\Models\Bookmark;
use App\Models\Peer;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Support\Format;
use App\Support\Strings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Torrent statistics and aggregate query helpers.
 *
 * Extracted from TorrentRepository to reduce god-object surface area.
 * Covers peer/snatch listing, speed/ratio calculations, and aggregate
 * lookups (comments, tags, bookmarks, snatch info, user-value fetch).
 */
class TorrentStatsService
{
    /**
     * @param  mixed  $torrentId
     * @return array<int|string, mixed>
     */
    public function listPeers($torrentId)
    {
        $seederList = $leecherList = collect();
        $peers = Peer::query()
            ->where('torrent', $torrentId)
            ->groupBy('peer_id')
            ->with(['user', 'relative_torrent'])
            ->get()
            ->groupBy('seeder');
        $seederGroup = $peers->get(PeerSeeder::YES->value);
        if ($seederGroup instanceof Collection) {
            $seederList = $seederGroup->sort(function ($a, $b) {
                $x = $a->uploaded;
                $y = $b->uploaded;
                if ($x == $y) {
                    return 0;
                }
                if ($x < $y) {
                    return 1;
                }

                return -1;
            });
            $seederList = $this->formatPeers($seederList);
        }
        $leecherGroup = $peers->get(PeerSeeder::NO->value);
        if ($leecherGroup instanceof Collection) {
            $leecherList = $leecherGroup->sort(function ($a, $b) {
                $x = $a->to_go;
                $y = $b->to_go;
                if ($x == $y) {
                    return 0;
                }
                if ($x < $y) {
                    return -1;
                }

                return 1;
            });
            $leecherList = $this->formatPeers($leecherList);
        }

        return [
            'seeder_list' => $seederList,
            'leecher_list' => $leecherList,
        ];

    }

    /** @param  mixed  $peer */
    public function getPeerUploadSpeed($peer): string
    {
        $diff = $peer->uploaded - $peer->uploadoffset;
        $seconds = max(1, $peer->started->diffInSeconds($peer->last_action, true));

        return Format::size($diff / $seconds).'/s';
    }

    /** @param  mixed  $peer */
    public function getPeerDownloadSpeed($peer): string
    {
        $diff = $peer->downloaded - $peer->downloadoffset;
        if ($peer->isSeeder()) {
            $seconds = max(1, $peer->started->diffInSeconds($peer->finishedat, true));
        } else {
            $seconds = max(1, $peer->started->diffInSeconds($peer->last_action, true));
        }

        return Format::size($diff / $seconds).'/s';
    }

    /** @param  mixed  $peer */
    public function getDownloadProgress($peer): string
    {
        return sprintf('%.2f%%', 100 * (1 - ($peer->to_go / $peer->relative_torrent->size)));
    }

    /**
     * @param  mixed  $peer
     * @return mixed
     */
    public function getShareRatio($peer)
    {
        if ($peer->downloaded) {
            $ratio = floor(($peer->uploaded / $peer->downloaded) * 1000) / 1000;
        } elseif ($peer->uploaded) {
            $ratio = 'Infinity';
        } else {
            $ratio = '---';
        }

        return $ratio;
    }

    /**
     * @param  mixed  $peers
     * @return mixed
     */
    private function formatPeers($peers)
    {
        foreach ($peers as &$item) {
            $item->upload_text = sprintf('%s@%s', Format::size($item->uploaded), $this->getPeerUploadSpeed($item));
            $item->download_text = sprintf('%s@%s', Format::size($item->downloaded), $this->getPeerDownloadSpeed($item));
            $item->download_progress = $this->getDownloadProgress($item);
            $item->share_ratio = $this->getShareRatio($item);
            $item->connect_time_total = $item->started->diffForHumans();
            $item->last_action_human = $item->last_action->diffForHumans();
            $item->agent_human = htmlspecialchars(Strings::userAgentClient($item->agent));
        }

        return $peers;
    }

    /**
     * @param  mixed  $torrentId
     * @return mixed
     */
    public function listSnatches($torrentId)
    {
        $snatches = Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('finished', SnatchFinished::YES->value)
            ->with(['user'])
            ->orderBy('completedat', 'desc')
            ->paginate();

        return $snatches;
    }

    /**
     * @param  mixed  $snatch
     * @return mixed
     */
    public function getSnatchUploadSpeed($snatch)
    {
        if ($snatch->seedtime <= 0) {
            $speed = Format::size(0);
        } else {
            $speed = Format::size($snatch->uploaded / ($snatch->seedtime + $snatch->leechtime));
        }

        return "$speed/s";
    }

    /**
     * @param  mixed  $snatch
     * @return mixed
     */
    public function getSnatchDownloadSpeed($snatch)
    {
        if ($snatch->leechtime <= 0) {
            $speed = Format::size(0);
        } else {
            $speed = Format::size($snatch->downloaded / $snatch->leechtime);
        }

        return "$speed/s";
    }

    /**
     * Get the latest comment for a torrent, or null if none exists.
     *
     * @return array<string, mixed>|null
     */
    public function getLastComment(int $torrentId): ?array
    {
        $lastcom = DB::table('comments')->where('torrent', $torrentId)->orderBy('id', 'desc')->first();

        return $lastcom ? array_merge((array) $lastcom, array_values((array) $lastcom)) : null;
    }

    /**
     * Get torrent tag records keyed by torrent id.
     *
     * @param  array<int, int>  $torrentIds
     * @return Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, TorrentTag>>
     */
    public function getTorrentTagsGrouped(array $torrentIds)
    {
        return TorrentTag::query()->whereIn('torrent_id', $torrentIds)->get()->groupBy('torrent_id');
    }

    /**
     * Fetch a torrent as an array for the legacy "torrent to user" value calculation.
     *
     * @return array<string, mixed>|null
     */
    public function findForUserValue(int $torrentId): ?array
    {
        return Torrent::query()->find($torrentId)?->toArray();
    }

    /**
     * Return the bookmarked torrent ids for a user.
     *
     * Mirrors the legacy {@see TorrentBookmark::bookmarkArray()}.
     *
     * @return array<int, int>
     */
    public function getBookmarkTorrentIds(int $userId): array
    {
        $rows = Bookmark::query()->where('userid', $userId)->pluck('torrentid')->all();

        if (empty($rows)) {
            return [0];
        }

        return array_map(fn ($id) => (int) $id, $rows);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getSnatchInfo(int|string $torrentId, int|string $userId): array|false
    {
        $record = DB::table('snatched')
            ->where('torrentid', (int) $torrentId)
            ->where('userid', (int) $userId)
            ->orderBy('id', 'desc')
            ->first();

        return $record ? (array) $record : false;
    }
}
