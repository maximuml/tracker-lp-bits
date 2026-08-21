<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentTag;
use Nexus\Database\NexusDB;

class TorrentDetailRepository
{
    /**
     * @return ?array<string, mixed>
     */
    public static function getTorrent(int $id): ?array
    {
        $torrent = NexusDB::table('torrents')
            ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
            ->leftJoin('sources', 'torrents.source', '=', 'sources.id')
            ->leftJoin('media', 'torrents.medium', '=', 'media.id')
            ->leftJoin('codecs', 'torrents.codec', '=', 'codecs.id')
            ->leftJoin('standards', 'torrents.standard', '=', 'standards.id')
            ->leftJoin('processings', 'torrents.processing', '=', 'processings.id')
            ->leftJoin('audiocodecs', 'torrents.audiocodec', '=', 'audiocodecs.id')
            ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
            ->where('torrents.id', $id)
            ->select(
                'torrents.*',
                'categories.name as cat_name',
                'categories.mode as search_box_id',
                'sources.name as source_name',
                'media.name as medium_name',
                'codecs.name as codec_name',
                'standards.name as standard_name',
                'processings.name as processing_name',
                'audiocodecs.name as audiocodec_name',
                'torrent_extras.descr as descr',
                'torrent_extras.nfo as nfo',
                'torrent_extras.media_info as technical_info',
            )
            ->first();

        if ($torrent === null) {
            return null;
        }

        return (array) $torrent;
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getMagicInfo(int $torrentId, int $currentUserId): array
    {
        $givers = NexusDB::table('magic')
            ->where('torrentid', $torrentId)
            ->orderByDesc('id')
            ->get(['userid', 'value']);

        $sumValue = 0;
        $whetherHaveGiveValue = 0;
        $addValue = '';
        foreach ($givers as $giver) {
            $sumValue += (int) $giver->value;
            if ((int) $giver->userid === $currentUserId) {
                $whetherHaveGiveValue = 1;
                $addValue = (int) $giver->value;
            }
        }

        return [
            'givers' => $givers,
            'count_user_number' => NexusDB::table('magic')
                ->where('torrentid', $torrentId)
                ->distinct()
                ->count('userid'),
            'sum_value' => $sumValue,
            'whether_have_give_value' => $whetherHaveGiveValue,
            'add_value' => $addValue,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getThanksInfo(int $torrentId, int $currentUserId): array
    {
        $thanks = NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['userid']);

        $hasThanked = false;
        foreach ($thanks as $t) {
            if ((int) $t->userid === $currentUserId) {
                $hasThanked = true;
                break;
            }
        }

        if (! $hasThanked) {
            $hasThanked = NexusDB::table('thanks')
                ->where('torrentid', $torrentId)
                ->where('userid', $currentUserId)
                ->exists();
        }

        return [
            'thanks' => $thanks,
            'count' => NexusDB::table('thanks')->where('torrentid', $torrentId)->count(),
            'has_thanked' => $hasThanked,
        ];
    }

    public static function getCommentCount(int $torrentId): int
    {
        return Comment::query()->where('torrent', $torrentId)->count();
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getComments(int $torrentId, int $offset, int $limit): array
    {
        return Comment::query()
            ->where('torrent', $torrentId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate'])
            ->toArray();
    }

    public static function incrementViews(int $id): void
    {
        Torrent::query()->where('id', $id)->increment('views');
    }

    /**
     * @return array<int, int>
     */
    public static function getTagIds(int $torrentId): array
    {
        return array_map(
            fn ($id) => (int) $id,
            TorrentTag::query()
                ->where('torrent_id', $torrentId)
                ->pluck('tag_id')
                ->toArray()
        );
    }

    public static function getLatestApprovalDenyLog(int $torrentId): ?TorrentOperationLog
    {
        return TorrentOperationLog::query()
            ->where('torrent_id', $torrentId)
            ->where('action_type', TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY)
            ->orderBy('id', 'desc')
            ->first();
    }
}
