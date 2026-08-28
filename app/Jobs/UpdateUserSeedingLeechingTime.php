<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Cache as AppCache;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateUserSeedingLeechingTime implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginUid, int $endUid, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginUid = $beginUid;
        $this->endUid = $endUid;
        $this->idStr = $idStr;
        $this->idRedisKey = $idRedisKey;
        $this->requestId = $requestId;
    }

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 3600;

    public int $backoff = 60;

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->idRedisKey)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf(
            '[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s, idStr: %s, idRedisKey: %s',
            $this->requestId, $this->beginUid, $this->endUid, $this->idStr, $this->idRedisKey,
        );
        Logger::writeWithContext((string) "{$logPrefix}, job start ...", (string) 'info', (bool) false);

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && ! empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = Cache::get($this->idRedisKey);
        }
        if (empty($idStr)) {
            Logger::writeWithContext((string) "{$logPrefix}, no idStr or idRedisKey", (string) 'error', (bool) false);

            return;
        }
        $userIdArr = array_filter(array_map('intval', explode(',', $idStr)));
        if (empty($userIdArr)) {
            Logger::writeWithContext((string) "{$logPrefix}, empty idStr", (string) 'error', (bool) false);

            return;
        }
        // 批量取，简单化
        $res = DB::table('snatched')
            ->selectRaw('userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum')
            ->whereIn('userid', $userIdArr)
            ->groupBy('userid')
            ->get();
        if ($res->isEmpty()) {
            Logger::writeWithContext((string) "{$logPrefix}, no data from idStr: {$idStr}", (string) 'error', (bool) false);

            return;
        }
        $snatchedMap = [];
        foreach ($res as $row) {
            $snatchedMap[(int) $row->userid] = [
                'seedtime' => (int) ($row->seedtime_sum ?? 0),
                'leechtime' => (int) ($row->leechtime_sum ?? 0),
            ];
        }
        $nowStr = now()->toDateTimeString();
        $rows = [];
        foreach ($userIdArr as $uid) {
            $rows[] = [
                'id' => $uid,
                'seedtime' => $snatchedMap[$uid]['seedtime'] ?? 0,
                'leechtime' => $snatchedMap[$uid]['leechtime'] ?? 0,
                'seed_time_updated_at' => $nowStr,
            ];
        }
        $result = DB::table('users')->upsert($rows, ['id'], ['seedtime', 'leechtime', 'seed_time_updated_at']);
        if ($delIdRedisKey) {
            AppCache::forgetWithLocales($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        Logger::writeWithContext((string) sprintf("{$logPrefix}, [DONE], update user count: %s, result: %s, cost time: %s seconds", count($rows), var_export($result, true), $costTime), (string) 'info', (bool) false);
        Logger::writeWithContext((string) "{$logPrefix}, upsert users seedtime/leechtime done", (string) 'debug', (bool) false);
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Logger::writeWithContext((string) ('failed: '.$exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
    }
}
