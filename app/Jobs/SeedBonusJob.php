<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Support\Bonus;
use App\Support\Cache as AppCache;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\Logger;
use App\Support\UserDisplay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeedBonusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginUid;

    private int $endUid;

    private string $idStr;

    private string $requestId;

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
        $this->onQueue('default');
    }

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 120;

    public int $backoff = 60;

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        if ($this->idRedisKey === '') {
            return [];
        }

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
            '[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_JOB], commonRequestId: %s, beginUid: %s, endUid: %s, idStr: %s, idRedisKey: %s',
            $this->requestId, $this->beginUid, $this->endUid, $this->idStr, $this->idRedisKey
        );
        Logger::writeWithContext("$logPrefix, job start ...");

        $haremAdditionFactor = SiteConfig::current()->bonus->haremAddition();
        $officialAdditionFactor = SiteConfig::current()->bonus->officialAddition();
        $donortimes_bonus = SiteConfig::current()->bonus->donorTimes();
        $autoclean_interval_one = SiteConfig::current()->main->autocleanIntervalOne();

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && ! empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = Cache::get($this->idRedisKey);
        }
        if (empty($idStr)) {
            Logger::writeWithContext("$logPrefix, no idStr or idRedisKey", 'error');

            return;
        }
        $idArr = array_filter(array_map('intval', explode(',', $idStr)));
        $results = DB::table('users')
            ->whereIn('id', $idArr)
            ->select(User::$commonFields)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        if (empty($results)) {
            Logger::writeWithContext("$logPrefix, no data from idStr: $idStr", 'error');

            return;
        }
        $logFile = Logger::filePath('seed-bonus-points');
        Logger::writeWithContext("$logPrefix, [GET_UID_REAL], count: ".count($results).", logFile: $logFile");
        $fd = fopen($logFile, 'a');
        $rows = [];
        $nowStr = now()->toDateTimeString();
        $logStr = '';
        foreach ($results as $userInfo) {
            $uid = $userInfo['id'];
            $isDonor = UserDisplay::isDonor($userInfo);
            $seedBonusResult = Bonus::calculateForUser($uid);
            $bonusLog = "[CLEANUP_CLI_CALCULATE_SEED_BONUS_HANDLE_USER], user: $uid, seedBonusResult: ".Json::encode($seedBonusResult);
            $all_bonus = $basicBonus = $seedBonusResult['seed_bonus'];
            $oldValue = $userInfo['seedbonus'];
            $bonusLog .= ", all_bonus: $all_bonus";
            $oldValue += $basicBonus;
            if ($isDonor && $donortimes_bonus != 0) {
                $donorAddition = $basicBonus * $donortimes_bonus;
                $all_bonus += $donorAddition;
                $bonusLog .= ", isDonor, donortimes_bonus: $donortimes_bonus, all_bonus: $all_bonus";
                $oldValue += $donorAddition;
            }
            if ($officialAdditionFactor > 0) {
                $officialAddition = $seedBonusResult['official_bonus'] * $officialAdditionFactor;
                $all_bonus += $officialAddition;
                $bonusLog .= ", officialAdditionFactor: $officialAdditionFactor, official_bonus: {$seedBonusResult['official_bonus']}, officialAddition: $officialAddition, all_bonus: $all_bonus";
                $oldValue += $officialAddition;
            }
            if ($haremAdditionFactor > 0) {
                $haremBonus = Bonus::haremAddition($uid);
                $haremAddition = (float) $haremBonus * (float) $haremAdditionFactor;
                $all_bonus += $haremAddition;
                $bonusLog .= ", haremAdditionFactor: $haremAdditionFactor, haremBonus: $haremBonus, haremAddition: $haremAddition, all_bonus: $all_bonus";
                $oldValue += $haremAddition;
            }
            if ($seedBonusResult['medal_additional_factor'] > 0) {
                $medalAddition = $seedBonusResult['medal_bonus'] * $seedBonusResult['medal_additional_factor'];
                $all_bonus += $medalAddition;
                $bonusLog .= ", medalAdditionFactor: {$seedBonusResult['medal_additional_factor']}, medalBonus: {$seedBonusResult['medal_bonus']}, medalAddition: $medalAddition, all_bonus: $all_bonus";
                $oldValue += $medalAddition;
            }
            Logger::writeWithContext((string) $bonusLog, (string) 'info', (bool) false);
            $dividend = 3600 / $autoclean_interval_one;
            $all_bonus = $all_bonus / $dividend;
            $seed_points = $seedBonusResult['seed_points'] / $dividend;
            $rows[] = [
                'id' => $uid,
                'seed_points' => (float) ($userInfo['seed_points'] ?? 0) + (float) $seed_points,
                'seed_points_per_hour' => (float) $seedBonusResult['seed_points'],
                'seed_bonus_per_hour' => (float) $seedBonusResult['seed_bonus'],
                'seedbonus' => (float) ($userInfo['seedbonus'] ?? 0) + (float) $all_bonus,
                'seeding_torrent_count' => (int) $seedBonusResult['torrent_peer_count'],
                'seeding_torrent_size' => (float) $seedBonusResult['size'],
                'seed_points_updated_at' => $nowStr,
            ];
            if ($fd) {
                $log = sprintf(
                    '%s|%s|%s|%s|%s|%s|%s|%s',
                    date('Y-m-d H:i:s'), $uid,
                    $userInfo['seed_points'], number_format($seed_points, 1, '.', ''), number_format($userInfo['seed_points'] + $seed_points, 1, '.', ''),
                    $userInfo['seedbonus'], number_format($all_bonus, 1, '.', ''), number_format($userInfo['seedbonus'] + $all_bonus, 1, '.', '')
                );
                $logStr .= $log.PHP_EOL;
            } else {
                Logger::writeWithContext((string) "logFile: {$logFile} is not writeable!", (string) 'error', (bool) false);
            }
        }
        $result = DB::table('users')->upsert($rows, ['id'], ['seed_points', 'seed_points_per_hour', 'seed_bonus_per_hour', 'seedbonus', 'seeding_torrent_count', 'seeding_torrent_size', 'seed_points_updated_at']);
        if ($delIdRedisKey) {
            AppCache::forgetWithLocales($this->idRedisKey);
        }
        if ($fd) {
            fwrite($fd, $logStr);
        }
        $costTime = time() - $beginTimestamp;
        Logger::writeWithContext((string) sprintf("{$logPrefix}, [DONE], update user count: %s, result: %s, cost time: %s seconds", count($rows), var_export($result, true), $costTime), (string) 'info', (bool) false);
        Logger::writeWithContext((string) "{$logPrefix}, upsert users seed bonus done", (string) 'debug', (bool) false);
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
