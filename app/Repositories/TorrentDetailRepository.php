<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TorrentOperationAction;
use App\Models\Comment;
use App\Models\File;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentState;
use App\Models\TorrentTag;
use App\Support\Cache\NexusCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class TorrentDetailRepository
{
    private const MAGIC_CACHE_TTL = 3600;

    private const THANKS_CACHE_TTL = 3600;

    public const THANKS_CACHE_KEY_PREFIX = 'td_thanks_';

    private const MAGIC_CACHE_KEY_PREFIX = 'td_magic_';

    private static function magicCacheKey(int $torrentId): string
    {
        return self::MAGIC_CACHE_KEY_PREFIX.$torrentId;
    }

    private static function thanksCacheKey(int $torrentId): string
    {
        return self::THANKS_CACHE_KEY_PREFIX.$torrentId;
    }

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
        $cache = NexusCache::instance();
        $shared = $cache?->get(self::magicCacheKey($torrentId));

        if (! is_array($shared)) {
            $givers = DB::table('magic')
                ->where('torrentid', $torrentId)
                ->orderByDesc('id')
                ->get(['userid', 'value']);

            $sumValue = 0;
            $giverIds = [];
            $sharedGivers = [];
            foreach ($givers as $giver) {
                $uid = (int) $giver->userid;
                $val = (int) $giver->value;
                $sumValue += $val;
                $giverIds[$uid] = true;
                $sharedGivers[] = ['userid' => $uid, 'value' => $val];
            }

            $shared = [
                'givers' => $sharedGivers,
                'count_user_number' => count($giverIds),
                'sum_value' => $sumValue,
            ];
            $cache?->put(self::magicCacheKey($torrentId), $shared, self::MAGIC_CACHE_TTL);
        }

        $whetherHaveGiveValue = 0;
        $addValue = '';
        foreach ($shared['givers'] as $giver) {
            if ($giver['userid'] === $currentUserId) {
                $whetherHaveGiveValue = 1;
                $addValue = $giver['value'];
                break;
            }
        }

        return [
            'givers' => array_map(static fn (array $giver): object => (object) $giver, $shared['givers']),
            'count_user_number' => $shared['count_user_number'],
            'sum_value' => $shared['sum_value'],
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

    public function insertMagic(int $torrentId, int $userId, int $value): int
    {
        $id = (int) DB::table('magic')->insertGetId([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
        ]);
        NexusCache::instance()?->forget(self::magicCacheKey($torrentId));

        return $id;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getThanksInfo(int $torrentId, int $currentUserId): array
    {
        $cache = NexusCache::instance();
        $shared = $cache?->get(self::thanksCacheKey($torrentId));

        if (! is_array($shared)) {
            $thanksRows = DB::table('thanks')
                ->where('torrentid', $torrentId)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['userid']);

            $shared = [
                'thanks' => $thanksRows
                    ->map(static fn (object $t): array => ['userid' => (int) $t->userid])
                    ->all(),
                'count' => DB::table('thanks')->where('torrentid', $torrentId)->count(),
            ];
            $cache?->put(self::thanksCacheKey($torrentId), $shared, self::THANKS_CACHE_TTL);
        }

        $hasThanked = false;
        foreach ($shared['thanks'] as $t) {
            if ($t['userid'] === $currentUserId) {
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
            'thanks' => array_map(static fn (array $t): object => (object) $t, $shared['thanks']),
            'count' => $shared['count'],
            'has_thanked' => $hasThanked,
        ];
    }

    public function getCommentCount(int $torrentId): int
    {
        return Comment::query()->where('torrent', $torrentId)->count();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(int $torrentId, int $offset, int $limit): Collection
    {
        return Comment::query()
            ->where('torrent', $torrentId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate']);
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
                ->all()
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

    /**
     * @return bool false when the (torrentid, userid) thank already exists —
     *              the unique key makes a concurrent duplicate insert lose
     */
    public function insertThanks(int $torrentId, int $userId): bool
    {
        $inserted = DB::table('thanks')->insertOrIgnore([
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]) === 1;
        if ($inserted) {
            NexusCache::instance()?->forget(self::thanksCacheKey($torrentId));
        }

        return $inserted;
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

    /** Torrent row for stats/sidecar rendering. */
    public function findById(int $id): ?Torrent
    {
        return Torrent::query()->find($id);
    }

    /**
     * Raw info_hash column for cache invalidation on torrent changes.
     */
    public function valueInfoHash(int $id): ?string
    {
        $value = Torrent::query()->where('id', $id)->value('info_hash');

        return $value === null ? null : (string) $value;
    }

    /**
     * Set-all global promotion state (`torrents_state.global_sp_state`).
     */
    public function updateGlobalSpState(int $state): int
    {
        return TorrentState::query()->update(['global_sp_state' => $state]);
    }
}
