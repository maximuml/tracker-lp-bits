<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TorrentOperationAction;
use App\Models\Comment;
use App\Models\File;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentTag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class TorrentDetailRepository
{
    /**
     * @return ?array<string, mixed>
     */
    public function getTorrent(int $id): ?array
    {
        $torrent = DB::table('torrents')
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
    public function getMagicInfo(int $torrentId, int $currentUserId): array
    {
        $givers = DB::table('magic')
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
            'count_user_number' => DB::table('magic')
                ->where('torrentid', $torrentId)
                ->distinct()
                ->count('userid'),
            'sum_value' => $sumValue,
            'whether_have_give_value' => $whetherHaveGiveValue,
            'add_value' => $addValue,
        ];
    }

    public function hasMagicRecord(int $torrentId, int $userId): bool
    {
        return DB::table('magic')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->exists();
    }

    public function insertMagic(int $torrentId, int $userId, int $value): void
    {
        DB::table('magic')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
        ]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getThanksInfo(int $torrentId, int $currentUserId): array
    {
        $thanks = DB::table('thanks')
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
            $hasThanked = DB::table('thanks')
                ->where('torrentid', $torrentId)
                ->where('userid', $currentUserId)
                ->exists();
        }

        return [
            'thanks' => $thanks,
            'count' => DB::table('thanks')->where('torrentid', $torrentId)->count(),
            'has_thanked' => $hasThanked,
        ];
    }

    public function getCommentCount(int $torrentId): int
    {
        return Comment::query()->where('torrent', $torrentId)->count();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getComments(int $torrentId, int $offset, int $limit): array
    {
        return Comment::query()
            ->where('torrent', $torrentId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate'])
            ->toArray();
    }

    public function incrementViews(int $id): void
    {
        Torrent::query()->where('id', $id)->increment('views');
    }

    /**
     * @return array<int, int>
     */
    public function getTagIds(int $torrentId): array
    {
        return array_map(
            fn ($id) => (int) $id,
            TorrentTag::query()
                ->where('torrent_id', $torrentId)
                ->pluck('tag_id')
                ->toArray()
        );
    }

    public function getLatestApprovalDenyLog(int $torrentId): ?TorrentOperationLog
    {
        return TorrentOperationLog::query()
            ->where('torrent_id', $torrentId)
            ->where('action_type', TorrentOperationAction::APPROVAL_DENY->value)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * @return Collection<int, File>
     */
    public function listFilesForTorrent(int $torrentId): Collection
    {
        return File::query()->where('torrent', $torrentId)->get();
    }

    /**
     * @return LengthAwarePaginator<int, Thank>
     */
    public function paginateThanks(int $torrentId): LengthAwarePaginator
    {
        return Thank::query()
            ->where('torrentid', $torrentId)
            ->whereHas('user')
            ->with(['user'])
            ->paginate();
    }

    /**
     * Latest comment row for a torrent.
     */
    public function getLastComment(int $torrentId): ?\stdClass
    {
        /** @var \stdClass|null $row */
        $row = DB::table('comments')->where('torrent', $torrentId)->orderBy('id', 'desc')->first();

        return $row;
    }

    /**
     * Latest comment per torrent.
     *
     * @param  array<int>  $torrentIds
     * @return SupportCollection<int|string, \stdClass> keyed by torrent id
     */
    public function listLastCommentsForTorrents(array $torrentIds): SupportCollection
    {
        return DB::table('comments')
            ->whereIn('id', function ($q) use ($torrentIds) {
                $q->selectRaw('MAX(id)')->from('comments')->whereIn('torrent', $torrentIds)->groupBy('torrent');
            })
            ->get()
            ->keyBy('torrent');
    }

    public function hasThanksRecord(int $torrentId, int $userId): bool
    {
        return DB::table('thanks')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->exists();
    }

    public function insertThanks(int $torrentId, int $userId): void
    {
        DB::table('thanks')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]);
    }

    /**
     * torrent_tags rows keyed by torrent id.
     *
     * @param  array<int, int>  $torrentIds
     * @return SupportCollection<int|string, Collection<int, TorrentTag>>
     */
    public function listTagsGroupedByTorrent(array $torrentIds): SupportCollection
    {
        return TorrentTag::query()->whereIn('torrent_id', $torrentIds)->get()->groupBy('torrent_id');
    }

    /** Plain-array torrent row for stats/sidecar rendering. */
    /**
     * @return array<string, mixed>|null
     */
    public function findArrayById(int $id): ?array
    {
        return Torrent::query()->find($id)?->toArray();
    }
}
