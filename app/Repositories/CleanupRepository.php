<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Jobs\SeedBonusJob;
use App\Jobs\UpdateTorrentSeedersEtc;
use App\Jobs\UpdateUserSeedingLeechingTime;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Time;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CleanupRepository extends BaseRepository
{
    const USER_SEED_BONUS_BATCH_KEY = 'batch_key:user_seed_bonus';

    const USER_SEEDING_LEECHING_TIME_BATCH_KEY = 'batch_key:user_seeding_leeching_time';

    const TORRENT_SEEDERS_ETC_BATCH_KEY = 'batch_key:torrent_seeders_etc';

    const IDS_KEY_PREFIX = 'cleanup_batch_job_ids:';

    /** @var array<int|string, mixed> */
    /** @var array<int|string, mixed> */
    /** @var array<int|string, mixed> */
    /** @var array<int|string, mixed> */
    /** @var array<int|string, mixed> */
    private static array $batchKeyActionsMap = [
        self::USER_SEED_BONUS_BATCH_KEY => [
            'action' => 'seed_bonus',
            'task_index' => 0,
        ],
        self::TORRENT_SEEDERS_ETC_BATCH_KEY => [
            'action' => 'seeders_etc',
            'task_index' => 1,
        ],
        self::USER_SEEDING_LEECHING_TIME_BATCH_KEY => [
            'action' => 'seeding_leeching_time',
            'task_index' => 2,
        ],
    ];

    private static int $totalTask = 3;

    private static float|int $oneTaskSeconds = 0;

    private static int $scanSize = 500;

    public function __construct(
        private readonly CleanupMonitorRepository $monitor = new CleanupMonitorRepository
    ) {}

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @return mixed
     */
    public function recordBatch(\Redis $redis, $uid, $torrentId)
    {
        $args = [
            self::USER_SEED_BONUS_BATCH_KEY, self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, self::TORRENT_SEEDERS_ETC_BATCH_KEY,
            $uid, $uid, $torrentId, $this->getHashKeySuffix(), $this->getCacheKeyLifeTime(), time(),
        ];
        $result = $redis->eval($this->getAddRecordLuaScript(), $args, 3);
        $err = $redis->getLastError();
        if ($err) {
            Logger::writeWithContext((string) "[REDIS_LUA_ERROR]: {$err}", (string) 'error', (bool) false);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function runBatchJobCalculateUserSeedBonus(string $requestId)
    {
        $this->runBatchJob(self::USER_SEED_BONUS_BATCH_KEY, $requestId);
    }

    /**
     * @return mixed
     */
    public function runBatchJobUpdateUserSeedingLeechingTime(string $requestId)
    {
        $this->runBatchJob(self::USER_SEEDING_LEECHING_TIME_BATCH_KEY, $requestId);
    }

    /**
     * @return mixed
     */
    public function runBatchJobUpdateTorrentSeedersEtc(string $requestId)
    {
        $this->runBatchJob(self::TORRENT_SEEDERS_ETC_BATCH_KEY, $requestId);
    }

    /**
     * @param  mixed  $batchKey
     * @param  mixed  $requestId
     * @return mixed
     */
    private function runBatchJob($batchKey, $requestId)
    {
        $redis = Redis::connection()->client();
        $logPrefix = sprintf("[$batchKey], commonRequestId: %s", $requestId);
        $beginTimestamp = time();
        if (! isset(self::$batchKeyActionsMap[$batchKey])) {
            Logger::writeWithContext((string) "{$logPrefix}, batchKey: {$batchKey} invalid", (string) 'error', (bool) false);

            return;
        }
        $batchKeyInfo = self::$batchKeyActionsMap[$batchKey];

        $batch = $this->getBatch($redis, $batchKey);
        if (! $batch) {
            Logger::writeWithContext((string) "{$logPrefix}, batchKey: {$batchKey} no batch...", (string) 'error', (bool) false);

            return;
        }
        // update the batch key
        // 用户魔力部分不更新，避免用户保旧种汇报时间过长影响魔力增加
        if ($batchKey != self::USER_SEED_BONUS_BATCH_KEY) {
            $newBatch = $batchKey.':'.$this->getHashKeySuffix();
            $lifeTime = $this->getCacheKeyLifeTime();
            $redis->set($batchKey, $newBatch, ['ex' => $lifeTime]);
            $redis->hSetNx($newBatch, -1, 1);
            $redis->expire($newBatch, $lifeTime);
        }

        $userSeedBonusDeadline = Time::deadThreshold(SiteConfig::current()->main->anninterthree());
        $count = 0;
        $it = null;
        $length = $redis->hLen($batch);
        $page = 0;
        /* Don't ever return an empty array until we're done iterating */
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while ($arr_keys = $redis->hScan($batch, $it, '*', self::$scanSize)) {
            $delay = $this->getDelay($batchKeyInfo['task_index'], $length, $page);
            $toRemoveFields = $validFields = [];
            foreach ($arr_keys as $field => $value) {
                if ($batchKey == self::USER_SEED_BONUS_BATCH_KEY && $value < $userSeedBonusDeadline) {
                    // dead, should remove
                    $toRemoveFields[] = $field;
                } else {
                    $validFields[] = $field;
                }
            }
            if (! empty($validFields)) {
                $idStr = implode(',', $validFields);
                $idRedisKey = self::IDS_KEY_PREFIX.Str::random();
                Cache::put($idRedisKey, $idStr);
                $action = $batchKeyInfo['action'];
                $delaySeconds = (int) $delay;
                if ($action === 'seed_bonus') {
                    SeedBonusJob::dispatch(0, 0, '', $idRedisKey, $requestId)->delay($delaySeconds);
                } elseif ($action === 'seeding_leeching_time') {
                    UpdateUserSeedingLeechingTime::dispatch(0, 0, '', $idRedisKey, $requestId)->delay($delaySeconds);
                } elseif ($action === 'seeders_etc') {
                    UpdateTorrentSeedersEtc::dispatch(0, 0, '', $idRedisKey, $requestId)->delay($delaySeconds);
                }
                Logger::writeWithContext((string) sprintf('[runBatchJob] dispatched %s job, idRedisKey: %s', $action, $idRedisKey), (string) 'info', (bool) false);
                $count += count($validFields);
            }
            if (! empty($toRemoveFields)) {
                $redis->hDel($batch, ...$toRemoveFields);
            }
            $page++;
        }

        // remove this batch
        if ($batchKey != self::USER_SEED_BONUS_BATCH_KEY) {
            $redis->unlink($batch);
        }
        $endTimestamp = time();
        Logger::writeWithContext((string) sprintf("{$logPrefix}, [DONE], batch: {$batch}, count: {$count}, cost time: %d seconds", $endTimestamp - $beginTimestamp), (string) 'info', (bool) false);
    }

    /**
     * @param  mixed  $batchKey
     * @return mixed
     */
    private function getBatch(\Redis $redis, $batchKey)
    {
        $batch = $redis->get($batchKey);
        if ($batch === false) {
            Logger::writeWithContext((string) "batchKey: {$batchKey}, no batch...", (string) 'error', (bool) false);

            return false;
        }
        if (! $redis->exists($batch)) {
            Logger::writeWithContext((string) "batch: {$batch}, not exists...", (string) 'error', (bool) false);

            return false;
        }

        return $batch;
    }

    /**
     * USER_SEED_BONUS, USER_SEEDING_LEECHING_TIME, TORRENT_SEEDERS_ETC,
     * uid, uid, torrentId, timeStr, cacheLifeTime, nowTimestamp
     */
    private function getAddRecordLuaScript(): string
    {
        return <<<'LUA'
local batchList = {KEYS[1], KEYS[2], KEYS[3]}
for k, v in pairs(batchList) do
    local batchKey = redis.call("GET", v)
    local isBatchKeyNew = false
    if batchKey == false then
        batchKey = v .. ":" .. ARGV[4]
        redis.call("SET", v, batchKey)
        if (k > 1) then
            redis.call("EXPIRE", v, ARGV[5])
        end
        isBatchKeyNew = true
    end
    local hashKey
    if (k == 1)
    then
        hashKey = ARGV[1]
    elseif (k == 2)
    then
        hashKey = ARGV[2]
    else
        hashKey = ARGV[3]
    end
    redis.call("HSET", batchKey, hashKey, ARGV[6])
    if (isBatchKeyNew and k > 1) then
        redis.call("EXPIRE", batchKey, ARGV[5])
    end
end
LUA;
    }

    private function getHashKeySuffix(): string
    {
        return date('Ymd_His');
    }

    private function getOneTaskSeconds(): float|int
    {
        if (self::$oneTaskSeconds == 0) {
            // 最低间隔，要在这个时间内执行掉全部任务
            $totalSeconds = SiteConfig::current()->main->autocleanIntervalOne();
            // 每个任务能分到的秒数，不能到顶，任务数+1计算
            self::$oneTaskSeconds = floor($totalSeconds / (self::$totalTask + 1));
        }

        return self::$oneTaskSeconds;
    }

    /** @param  mixed  $taskIndex */
    private function getDelayBase($taskIndex): float|int
    {
        return $this->getOneTaskSeconds() * $taskIndex;
    }

    private function getDelay(int $taskIndex, int $length, int $page): float
    {
        // 超始基数
        $base = $this->getDelayBase($taskIndex);
        // 一共有这么多时间可以使用
        $totalSeconds = $this->getOneTaskSeconds();
        // 分几份
        $totalPage = ceil($length / self::$scanSize);
        // 每份多长
        $perPage = floor($totalSeconds / $totalPage);
        // page 从 0 开始
        $offset = $page * $perPage;

        return floor($base + $offset);
    }

    private function getCacheKeyLifeTime(): int
    {
        $four = $this->getInterval('four');
        $three = $this->getInterval('three');
        $one = $this->getInterval('one');

        return intval($four) + intval($three) + intval($one);
    }

    /** @param  mixed  $level */
    private function getInterval($level): int
    {
        return SiteConfig::current()->main->autocleanInterval((string) $level);
    }

    public function checkCleanup(): void
    {
        $this->monitor->checkCleanup();
    }

    public function checkQueueFailedJobs(): void
    {
        $this->monitor->checkQueueFailedJobs();
    }
}
