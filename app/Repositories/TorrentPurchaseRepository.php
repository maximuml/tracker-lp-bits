<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ModelEventEnum;
use App\Models\Snatch;
use App\Models\TorrentBuyLog;
use App\Support\Events;
use App\Support\Logger;
use Illuminate\Support\Facades\Redis;

/**
 * Handles paid-torrent purchase state and Redis cache logic.
 *
 * Extracted from TorrentRepository to reduce god-object surface area.
 */
class TorrentPurchaseRepository extends BaseRepository
{
    public const BOUGHT_USER_CACHE_KEY_PREFIX = 'torrent_purchasers';

    public const BUY_FAIL_CACHE_KEY_PREFIX = 'torrent_purchase_fails';

    public const BUY_STATUS_SUCCESS = 0;

    public const BUY_STATUS_NOT_YET = -1;

    public const BUY_STATUS_UNKNOWN = -2;

    /** @param  mixed  $torrentId */
    public function loadBoughtUser($torrentId): int
    {
        $size = 500;
        $page = 1;
        $redis = Redis::connection()->client();
        $total = 0;
        while (true) {
            $list = TorrentBuyLog::query()->where('torrent_id', $torrentId)->forPage($page, $size)->get(['torrent_id', 'uid']);
            if ($list->isEmpty()) {
                break;
            }
            foreach ($list as $item) {
                $key = $this->getBoughtUserCacheKey($torrentId, $item->uid);
                $redis->set($key, 1, ['EX' => 86400 * 30]);
                $total += 1;
                Logger::writeWithContext((string) sprintf('set %s 1', $key), (string) 'info', (bool) false);
            }
            $page++;
        }
        Logger::writeWithContext((string) "torrent_purchasers:{$torrentId} LOAD DONE, total: {$total}", (string) 'info', (bool) false);

        return $total;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $buyLogId
     *
     * @throws \RedisException
     */
    public function addBuySuccessCache($uid, $torrentId, $buyLogId): void
    {
        Redis::connection()->client()->set($this->getBoughtUserCacheKey($torrentId, $uid), 1, ['NX', 'EX' => 86400 * 30]);
        $record = Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('userid', $uid)
            ->first();
        if ($record) {
            $record->buy_log_id = $buyLogId;
            $record->save();
            Events::publishModel(ModelEventEnum::SNATCHED_UPDATED, $record->id, '');
        } else {
            Logger::writeWithContext((string) "addBuySuccessCache, uid: {$uid}, torrentId: {$torrentId}, buyLogId: {$buyLogId}, snatched not exists", (string) 'error', (bool) false);
        }

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccessCache($uid, $torrentId): bool
    {
        $key = $this->getBoughtUserCacheKey($torrentId, $uid);
        if (Redis::connection()->client()->exists($key)) {
            return true;
        }

        return false;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccess($uid, $torrentId): bool
    {
        if ($this->hasBuySuccessCache($uid, $torrentId)) {
            return true;
        }
        $buyLog = TorrentBuyLog::query()
            ->where('torrent_id', $torrentId)
            ->where('uid', $uid)
            ->first();
        if ($buyLog) {
            $this->addBuySuccessCache($uid, $torrentId, $buyLog->id);
        }

        return $buyLog != null;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function getBuyStatus($uid, $torrentId): int
    {
        if ($this->hasBuySuccess($uid, $torrentId)) {
            return self::BUY_STATUS_SUCCESS;
        }
        $buyFailCount = $this->getBuyFailCache($uid, $torrentId);
        if ($buyFailCount > 0) {
            return $buyFailCount;
        }

        return self::BUY_STATUS_UNKNOWN;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     *
     * @throws \RedisException
     */
    public function addBuyFailCache($uid, $torrentId): void
    {
        $key = $this->getBuyFailCacheKey((int) $uid, (int) $torrentId);
        $result = Redis::connection()->client()->incr($key);
        if ($result == 1) {
            Redis::connection()->client()->expire($key, 3600);
        }
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     *
     * @throws \RedisException
     */
    public function getBuyFailCache($uid, $torrentId): int
    {
        return intval(Redis::connection()->client()->get($this->getBuyFailCacheKey((int) $uid, (int) $torrentId)));
    }

    /**
     * @param  mixed  $torrentId
     * @param  mixed  $userId
     */
    public function getBoughtUserCacheKey($torrentId, $userId): string
    {
        return sprintf('%s:%s:%s', self::BOUGHT_USER_CACHE_KEY_PREFIX, $torrentId, $userId);
    }

    public function getBuyFailCacheKey(int $userId, int $torrentId): string
    {
        return sprintf('%s:%s:%s', self::BUY_FAIL_CACHE_KEY_PREFIX, $userId, $torrentId);
    }
}
