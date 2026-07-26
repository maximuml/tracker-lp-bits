<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Repositories\CleanupRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

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

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array<int, \Illuminate\Queue\Middleware\WithoutOverlapping>
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
            "[CLEANUP_CLI_UPDATE_SEEDING_LEECHING_TIME_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s, idStr: %s, idRedisKey: %s",
            $this->requestId, $this->beginUid, $this->endUid, $this->idStr, $this->idRedisKey,
        );
        do_log("$logPrefix, job start ...");

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && !empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = NexusDB::cache_get($this->idRedisKey);
        }
        if (empty($idStr)) {
            do_log("$logPrefix, no idStr or idRedisKey", "error");
            return;
        }
        $userIdArr = array_filter(array_map('intval', explode(",", $idStr)));
        if (empty($userIdArr)) {
            do_log("$logPrefix, empty idStr", "error");
            return;
        }
        //批量取，简单化
//        $res = sql_query("select userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum from snatched group by userid where userid in ($idStr)");
        $res = NexusDB::table("snatched")
            ->selectRaw("userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum")
            ->whereIn('userid', $userIdArr)
            ->groupBy("userid")
            ->get();
        if ($res->isEmpty()) {
            do_log("$logPrefix, no data from idStr: $idStr", "error");
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
        $result = NexusDB::table('users')->upsert($rows, ['id'], ['seedtime', 'leechtime', 'seed_time_updated_at']);
        if ($delIdRedisKey) {
            NexusDB::cache_del($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        do_log(sprintf(
            "$logPrefix, [DONE], update user count: %s, result: %s, cost time: %s seconds",
            count($rows), var_export($result, true), $costTime
        ));
        do_log("$logPrefix, upsert users seedtime/leechtime done", "debug");
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        do_log("failed: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
    }
}
