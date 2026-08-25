<?php

namespace App\Repositories;

use App\Models\RequireSeedTorrent;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\UserRequireSeedTorrent;
use App\Support\Logger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class RequireSeedTorrentRepository extends BaseRepository
{
    public function autoAddToListCronjob(): void
    {
        $logPrefix = '[RequireSeedTorrentRepository.autoAddToListCronjob]';
        $enabled = Setting::getIsRequireSeedSectionEnabled();
        if (! $enabled) {
            Logger::writeWithContext((string) "{$logPrefix}, not enabled", (string) 'info', (bool) false);

            return;
        }
        $countMaxAllowed = Setting::getRequireSeedSectionTorrentCountMax();
        $countNow = RequireSeedTorrent::query()->count();
        if ($countNow >= $countMaxAllowed) {
            Logger::writeWithContext((string) "{$logPrefix}, max allowed {$countMaxAllowed} reached", (string) 'info', (bool) false);

            return;
        }
        $count = $countMaxAllowed - $countNow;
        $seederMax = Setting::getRequireSeedSectionSeederLte();
        $seederMin = Setting::getRequireSeedSectionSeederGte();
        $logPrefix .= ", countMaxAllowed: $countMaxAllowed, countNow: $countNow, count: $count, seederMax: $seederMax, seederMin: $seederMin";
        $query = Torrent::query()->where('banned', 'no')
            ->where('seeders', '<=', $seederMax)
            ->where('seeders', '>=', $seederMin);
        $tags = Setting::getRequireSeedSectionTags();
        if (! empty($tags)) {
            $logPrefix .= ', tags: '.implode(',', $tags);
            $query->whereHas('torrent_tags', function ($query) use ($tags) {
                $query->whereIn('tag_id', $tags);
            });
        }
        $list = $query->leftJoin('require_seed_torrents', 'torrents.id', '=', 'require_seed_torrents.torrent_id')
            ->whereNull('require_seed_torrents.id')
            ->orderBy('torrents.seeders', 'asc')
            ->orderBy('torrents.times_completed', 'desc')
            ->orderBy('torrents.hits', 'desc')
            ->limit($count)
            ->get(['torrents.id']);
        $data = [];
        $nowStr = now()->toDateTimeString();
        $redis = Redis::connection()->client();
        $cacheKey = self::getTorrentCacheKey();
        foreach ($list as $item) {
            $data[] = [
                'torrent_id' => $item->id,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            $redis->hset($cacheKey, $item->id, $nowStr);
        }
        RequireSeedTorrent::query()->insert($data);
        Logger::writeWithContext((string) ("{$logPrefix}, success inserted: ".count($data)), (string) 'info', (bool) false);
    }

    public function autoRemoveFromListCronjob(): void
    {
        $idArr = RequireSeedTorrent::query()->pluck('torrent_id')->toArray();
        if (empty($idArr)) {
            Logger::writeWithContext((string) 'no data to remove', (string) 'info', (bool) false);

            return;
        }
        $seederMax = Setting::getRequireSeedSectionSeederLte();
        $seederMin = Setting::getRequireSeedSectionSeederGte();
        $torrents = Torrent::query()->whereIn('id', $idArr)
            ->where('seeders', '<', $seederMin)
            ->get(['id']);
        if (! empty($torrents)) {
            $this->doRemove($torrents);
            Logger::writeWithContext((string) sprintf('remove %s seeders < %s', count($torrents), $seederMin), (string) 'info', (bool) false);
        }
        $torrents = Torrent::query()->whereIn('id', $idArr)
            ->where('seeders', '>', $seederMax)
            ->get(['id']);
        if (! empty($torrents)) {
            $this->doRemove($torrents);
            Logger::writeWithContext((string) sprintf('remove %s seeders > %s', count($torrents), $seederMax), (string) 'info', (bool) false);
        }
    }

    /** @param  Collection<int, mixed>  $torrents */
    public function doRemove(Collection $torrents): void
    {
        $idArr = [];
        $redis = Redis::connection()->client();
        $promotionState = Setting::getRequireSeedSectionPromotionState();
        $ttlInSeconds = 24 * 3600;
        foreach ($torrents as $torrent) {
            $idArr[] = $torrent->id;
            $promotionStateCacheKey = sprintf('%s:%s', Torrent::REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE_KEY, $torrent->id);
            $redis->setex($promotionStateCacheKey, $ttlInSeconds, $promotionState);
            // remove torrent from list
            $redis->hDel(self::getTorrentCacheKey(), $torrent->id);
            // remove all users under torrent
            $redis->unlink(self::getTorrentUserCacheKey($torrent->id));
        }
        RequireSeedTorrent::query()->whereIn('torrent_id', $idArr)->delete();
        UserRequireSeedTorrent::query()->whereIn('torrent_id', $idArr)->delete();
        Logger::writeWithContext((string) ('success removed '.count($idArr)), (string) 'info', (bool) false);
    }

    private static function getTorrentCacheKey(): string
    {
        return Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY;
    }

    /** @param  mixed  $torrentId */
    private static function getTorrentUserCacheKey($torrentId): string
    {
        return sprintf('%s:%s', Torrent::REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY, $torrentId);
    }

    /**
     * @param  mixed  $userId
     * @param  mixed  $torrentId
     */
    public static function shouldRecordUser(\Redis $redis, $userId, $torrentId): bool
    {
        $logPrefix = "userId: $userId, torrentId: $torrentId";
        // check enabled or not
        if (! Setting::getIsRequireSeedSectionEnabled()) {
            Logger::writeWithContext((string) "{$logPrefix}, not enabled", (string) 'debug', (bool) false);

            return false;
        }
        // first, torrent on list
        $onListCacheKey = self::getTorrentCacheKey();
        if (! $redis->hExists($onListCacheKey, $torrentId)) {
            Logger::writeWithContext((string) "{$logPrefix}, torrent not on list: {$onListCacheKey}", (string) 'debug', (bool) false);

            return false;
        }
        // second, torrent user not exists
        $torrentUserCacheKey = self::getTorrentUserCacheKey($torrentId);
        if ($redis->hExists($torrentUserCacheKey, $userId)) {
            Logger::writeWithContext((string) "{$logPrefix}, user already exists: {$torrentUserCacheKey}", (string) 'debug', (bool) false);

            return false;
        }

        return true;
    }

    /**
     * @param  mixed  $userId
     * @param  mixed  $torrentId
     * @param  array<int|string, mixed>  $snatchedInfo
     */
    public static function recordUser(\Redis $redis, $userId, $torrentId, array $snatchedInfo): void
    {
        $torrentUserCacheKey = self::getTorrentUserCacheKey($torrentId);
        $nowStr = now()->toDateTimeString();
        $values = [
            'user_id' => $userId,
            'torrent_id' => $torrentId,
            'seed_time_begin' => $snatchedInfo['seedtime'],
            'uploaded_begin' => $snatchedInfo['uploaded'],
            'created_at' => $nowStr,
        ];
        $uniqueBy = ['user_id', 'torrent_id'];
        $update = ['updated_at'];
        UserRequireSeedTorrent::query()->upsert($values, $uniqueBy, $update);
        $redis->hset($torrentUserCacheKey, $userId, $nowStr);
        Logger::writeWithContext((string) "success insert user: {$userId}, torrent: {$torrentId}", (string) 'info', (bool) false);
    }

    public function autoSettlementCronjob(): void {}
}
