<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Attendance;
use App\Support\Environment;
use App\Support\LegacyDb;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off attendance migrations and cleanup: legacy row consolidation,
 * per-user dedup, and continuous-day log backfill.
 *
 * Extracted from AttendanceRepository to keep both classes under the
 * 400-line ratchet.
 */
class AttendanceMigrationRepository
{
    /**
     * 将旧的 1 人 1 天 1 条迁移到新版 1 人一条
     */
    public function migrateAttendance(): int
    {
        $page = 1;
        $size = 10000;
        $caseWhens = [];
        $idArr = [];
        $table = 'attendance';
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE], page: $page, size: $size";
            // as soon as possible, don't use eloquent
            $result = DB::table($table)
                ->groupBy(['uid'])
                ->selectRaw('uid, max(id) as id, count(*) as counts')
                ->forPage($page, $size)
                ->get();
            Logger::writeWithContext((string) ("{$logPrefix}, ".LegacyDb::lastQuery(false, 'json').', count: '.$result->count()), (string) 'info', (bool) false);
            if ($result->isEmpty()) {
                Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            foreach ($result as $row) {
                $caseWhens[] = sprintf('when %d then %d', (int) $row->id, (int) $row->counts);
                $idArr[] = (int) $row->id;
                Logger::writeWithContext((string) sprintf("{$logPrefix}, update user: %s(ID: %s) => %s", $row->uid, $row->id, $row->counts), (string) 'info', (bool) false);
            }
            $page++;
        }
        if (empty($caseWhens)) {
            Logger::writeWithContext((string) 'no data to update...', (string) 'info', (bool) false);

            return 0;
        }
        $caseWhenStr = sprintf('case id %s end', implode(' ', $caseWhens));
        $result = DB::table($table)
            ->whereIn('id', $idArr)
            ->update(['total_days' => DB::raw($caseWhenStr)]); // @phpstan-ignore argument.type

        Logger::writeWithContext((string) ("[MIGRATE_ATTENDANCE] DONE! {$caseWhenStr}, result: ".var_export($result, true)), (string) 'info', (bool) false);

        return count($idArr);
    }

    /**
     * 清理签到记录，每人只保留一条
     */
    public function cleanup(): int
    {
        $query = Attendance::query()->groupBy('uid')->havingRaw('count(*) > 1')->selectRaw('uid, max(id) as max_id');
        $page = 1;
        $size = 10000;
        $deleteCounts = 0;
        while (true) {
            $rows = $query->forPage($page, $size)->get();
            $log = 'sql: '.LegacyDb::lastQuery(false, 'json').', count: '.$rows->count();
            Logger::writeWithContext((string) $log, (string) 'info', (bool) Environment::isConsole());
            if ($rows->isEmpty()) {
                $log = 'no more data....';
                Logger::writeWithContext((string) $log, (string) 'info', (bool) Environment::isConsole());
                break;
            }
            foreach ($rows as $row) {
                do {
                    $deleted = Attendance::query()
                        ->where('uid', $row->uid)
                        ->where('id', '<', $row->max_id)
                        ->limit(10000)
                        ->delete();
                    $log = "delete: $deleted by sql: ".LegacyDb::lastQuery(false, 'json');
                    $deleteCounts += $deleted;
                    Logger::writeWithContext((string) $log, (string) 'info', (bool) Environment::isConsole());
                } while ($deleted > 0);
            }
            $page++;
        }

        return $deleteCounts;
    }

    /**
     * 为 1.7 新的补签功能回写当前连续签到记录
     *
     * @param  mixed  $uid
     */
    public function migrateAttendanceLogs($uid = 0): int
    {
        $cleanUpCounts = $this->cleanup();
        Logger::writeWithContext((string) "cleanup count: {$cleanUpCounts}", (string) 'info', (bool) Environment::isConsole());

        $page = 1;
        $size = 10000;
        $rows = [];
        $nowStr = now()->toDateTimeString();
        while (true) {
            $logPrefix = "[MIGRATE_ATTENDANCE_LOGS], page: $page, size: $size";
            $query = Attendance::query()
                ->where('added', '>=', Carbon::yesterday())
                ->forPage($page, $size);
            if ($uid) {
                $query->where('uid', $uid);
            }
            $result = $query->get();
            Logger::writeWithContext((string) ("{$logPrefix}, ".LegacyDb::lastQuery(false, 'json').', count: '.$result->count()), (string) 'info', (bool) Environment::isConsole());
            if ($result->isEmpty()) {
                Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            foreach ($result as $row) {
                $interval = \DateInterval::createFromDateString('-1 day');
                $period = new \DatePeriod($row->added->addDays(1), $interval, $row->days, \DatePeriod::EXCLUDE_START_DATE);
                $i = 0;
                foreach ($period as $periodValue) {
                    $rows[] = [
                        'uid' => (int) $row->uid,
                        'points' => $i == 0 ? (int) $row->points : 0,
                        'date' => $periodValue->format('Y-m-d'),
                        'created_at' => $nowStr,
                        'updated_at' => $nowStr,
                    ];
                    $i++;
                }
            }
            $page++;
        }
        if (empty($rows)) {
            Logger::writeWithContext((string) 'no data to insert...', (string) 'info', (bool) Environment::isConsole());

            return 0;
        }
        DB::table('attendance_logs')->upsert($rows, ['uid', 'date'], ['points', 'updated_at']);
        $insertCount = count($rows);
        Logger::writeWithContext((string) ('[MIGRATE_ATTENDANCE_LOGS] DONE! insert count: '.$insertCount), (string) 'info', (bool) Environment::isConsole());

        return $insertCount;
    }
}
