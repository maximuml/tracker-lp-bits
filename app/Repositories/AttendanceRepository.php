<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Environment;
use App\Support\Input;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceRepository extends BaseRepository
{
    /**
     * @param  mixed  $uid
     * @return mixed
     */
    public function attend($uid)
    {
        $attendance = $this->getAttendance($uid);
        $now = Carbon::now();
        $today = Carbon::today();
        $config = SiteConfig::current()->bonus;
        $initialBonus = $config->attendanceInitial(Attendance::INITIAL_BONUS);
        $isUpdated = 1;
        $initialData = [
            'uid' => $uid,
            'added' => $now,
            'points' => $initialBonus,
            'days' => 1,
            'total_days' => 1,
        ];
        $update = $initialData;
        if (! $attendance) {
            // first time
            Logger::writeWithContext((string) ('[DO_INSERT]: '.Json::encode($initialData)), (string) 'info', (bool) false);
            $attendance = Attendance::query()->create($initialData);
        } else {
            $added = $attendance->added->startOfDay();
            Logger::writeWithContext((string) ('[ORIGINAL_DATA]: '.$attendance->toJson()), (string) 'info', (bool) false);
            if ($added->gte($today)) {
                // already attended today, do nothing
                $isUpdated = 0;
            } else {
                $diffDays = $today->diffInDays($added, true);
                if ($diffDays == 1) {
                    // yesterday do it, it's continuous
                    $continuousDays = $this->getContinuousDays($attendance, Carbon::yesterday());
                    $points = $this->getContinuousPoints($continuousDays + 1);
                    Logger::writeWithContext((string) "[CONTINUOUS] continuous days from yesterday: {$continuousDays}, points: {$points}", (string) 'info', (bool) false);
                    $update = [
                        'added' => $now,
                        'points' => $points,
                        'days' => $continuousDays + 1,
                        'total_days' => $attendance->total_days + 1,
                    ];
                } else {
                    // not continuous
                    Logger::writeWithContext((string) '[NOT_CONTINUOUS]', (string) 'info', (bool) false);
                    $update['total_days'] = $attendance->total_days + 1;
                }
                Logger::writeWithContext((string) ('[DO_UPDATE]: '.Json::encode($update)), (string) 'info', (bool) false);
                $attendance->update($update);
            }
        }
        if ($isUpdated) {
            User::query()->where('id', $uid)->increment('seedbonus', $update['points']);
            $attendanceLog = [
                'uid' => $attendance->uid,
                'points' => $update['points'],
                'date' => $now->format('Y-m-d'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            AttendanceLog::query()->insert($attendanceLog);
        }
        $attendance->added_time = $now->toTimeString();
        $attendance->is_updated = $isUpdated;
        $baseQuery = AttendanceLog::query()->where('date', $today->format('Y-m-d'));
        $attendance->today_counts = (clone $baseQuery)->count();
        $myLog = (clone $baseQuery)->where('uid', $uid)->first(['id']);
        $myId = $myLog instanceof AttendanceLog ? $myLog->id : 0;
        $attendance->my_ranking = (clone $baseQuery)->where('id', '<=', $myId)->count();
        Logger::writeWithContext((string) ('[FINAL_ATTENDANCE]: '.$attendance->toJson()), (string) 'info', (bool) false);

        return $attendance;

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $date
     * @return mixed
     */
    public function getAttendance($uid, $date = '')
    {
        $query = Attendance::query()
            ->where('uid', $uid)
            ->orderBy('id', 'desc');
        if (! empty($date)) {
            $query->where('added', '>=', Carbon::parse($date)->startOfDay())
                ->where('added', '<=', Carbon::parse($date)->endOfDay());
        }

        return $query->first();
    }

    /**
     * @param  mixed  $days
     * @return mixed
     */
    public function getContinuousPoints($days)
    {
        $config = SiteConfig::current()->bonus;
        $initial = $config->attendanceInitial(Attendance::INITIAL_BONUS);
        $step = $config->attendanceStep(Attendance::STEP_BONUS);
        $max = $config->attendanceMax(Attendance::MAX_BONUS);
        $extraAwards = $config->attendanceContinuous(Attendance::CONTINUOUS_BONUS);
        $points = min($initial + ($days - 1) * $step, $max);
        krsort($extraAwards);
        foreach ($extraAwards as $key => $value) {
            if ($days == $key) {
                $points += $value;
                break;
            }
        }

        return $points;
    }

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

    /**
     * @param  mixed  $start
     */
    public function getContinuousDays(Attendance $attendance, $start): int
    {
        $start = Carbon::parse($start);
        $logQuery = $attendance->logs()->where('date', '<=', $start->format('Y-m-d'))->orderBy('date', 'desc');
        $attendanceLogs = $logQuery->get(['date'])->keyBy('date');
        $counts = $attendanceLogs->count();
        Logger::writeWithContext((string) sprintf('user: %s, log counts: %s from query: %s', $attendance->uid, $counts, LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
        if ($counts == 0) {
            return 0;
        }
        $interval = \DateInterval::createFromDateString('-1 day');
        $period = new \DatePeriod($start->clone()->addDays(1), $interval, $counts, \DatePeriod::EXCLUDE_START_DATE);
        $days = 0;
        foreach ($period as $value) {
            $checkDate = $value->format('Y-m-d');
            if ($attendanceLogs->has($checkDate)) {
                $days++;
                Logger::writeWithContext((string) sprintf('user: %s, date: %s, [HAS_ATTENDANCE], now days: %s', $attendance->uid, $checkDate, $days), (string) 'info', (bool) false);
            } else {
                Logger::writeWithContext((string) sprintf('user: %s, date: %s, [NOT_ATTENDANCE], now days: %s', $attendance->uid, $checkDate, $days), (string) 'info', (bool) false);
                break;
            }
        }

        return $days;

    }

    /**
     * @param  mixed  $user
     * @param  mixed  $dateStr
     * @return mixed
     */
    public function retroactive($user, $dateStr)
    {
        if (! $user instanceof User) {
            $user = User::query()->findOrFail((int) $user);
        }
        $attendance = $this->getAttendance($user->id);
        if (! $attendance) {
            throw new \LogicException(Locale::trans('attendance.have_not_attendance_yet', [], null));
        }
        $date = Carbon::parse($dateStr);
        $now = Carbon::now();
        if ($date->gte($now) || $now->diffInDays($date, true) > Attendance::MAX_RETROACTIVE_DAYS) {
            throw new \LogicException(Locale::trans('attendance.target_date_can_no_be_retroactive', ['date' => $date->format('Y-m-d')], null));
        }

        return DB::transaction(function () use ($user, $attendance, $date) {
            if (AttendanceLog::query()->where('uid', $user->id)->where('date', $date->format('Y-m-d'))->exists()) {
                throw new \RuntimeException(Locale::trans('attendance.already_attendance', [], null));
            }
            if ($user->attendance_card < 1) {
                throw new \RuntimeException(Locale::trans('attendance.card_not_enough', [], null));
            }
            $log = sprintf('user: %s, card: %s, retroactive date: %s', $user->id, $user->attendance_card, $date->format('Y-m-d'));
            $continuousDays = $this->getContinuousDays($attendance, $date->clone()->subDays(1));
            $log .= ", continuousDays from prev day: $continuousDays";
            $points = $this->getContinuousPoints($continuousDays + 1);
            $log .= ", points: $points";
            Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
            $userUpdates = [
                'attendance_card' => DB::raw(DB::getQueryGrammar()->wrap('attendance_card').' - 1'), // @phpstan-ignore argument.type
                'seedbonus' => DB::raw(DB::getQueryGrammar()->wrap('seedbonus').' + '.(float) $points), // @phpstan-ignore argument.type
            ];
            $affectedRows = User::query()
                ->where('id', $user->id)
                ->where('attendance_card', $user->attendance_card)
                ->update($userUpdates);
            $msg = 'Decrement user attendance_card and increment bonus';
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ("{$msg} fail, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                throw new \RuntimeException("$msg fail");
            }
            Logger::writeWithContext((string) ("{$msg} success, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
            $insert = [
                'uid' => $user->id,
                'points' => $points,
                'date' => $date,
                'is_retroactive' => 1,
            ];
            $attendanceLog = AttendanceLog::query()->create($insert);
            // Increment total days and update days.
            $attendance->update([
                'total_days' => DB::raw(DB::getQueryGrammar()->wrap('total_days').' + 1'), // @phpstan-ignore argument.type
                'days' => $this->getContinuousDays($attendance, Carbon::today()),
            ]);

            return $attendanceLog;
        });
    }

    /**
     * Build all data required by the attendance page view.
     *
     * @return array<string, mixed>
     */
    public function buildViewData(?Attendance $attendance, int $uid): array
    {
        $today = Carbon::today();
        $tomorrow = $today->clone()->addDay();
        $end = $today->clone()->endOfMonth();
        $start = $today->clone()->subMonths(2);

        $hasAttendedToday = $attendance !== null && $attendance->added && $attendance->added->isSameDay($today);

        $todayCounts = 0;
        $myRanking = 0;
        $logs = collect();
        $events = [];
        $validRange = [
            'start' => $start->format('Y-m-d'),
            'end' => $end->clone()->addDay()->format('Y-m-d'),
        ];

        if ($hasAttendedToday) {
            $todayDate = $today->format('Y-m-d');
            $baseQuery = AttendanceLog::query()->where('date', $todayDate);
            $todayCounts = $baseQuery->count();
            $myLog = (clone $baseQuery)->where('uid', $uid)->first(['id']);
            if ($myLog) {
                $myRanking = (clone $baseQuery)->where('id', '<=', $myLog->id)->count();
            }

            $logs = AttendanceLog::query()
                ->where('uid', $uid)
                ->where('date', '>=', $start->format('Y-m-d'))
                ->get()
                ->keyBy('date');

            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($start, $interval, $end);
            foreach ($period as $value) {
                if ($value >= $tomorrow) {
                    continue;
                }
                $checkDate = $value->format('Y-m-d');
                $eventBase = ['start' => $checkDate, 'end' => $checkDate];
                if ($logs->has($checkDate)) {
                    $logValue = $logs->get($checkDate);
                    if (! $logValue instanceof AttendanceLog) {
                        continue;
                    }
                    $events[] = array_merge($eventBase, ['display' => 'background']);
                    if ($logValue->points > 0) {
                        $events[] = array_merge($eventBase, ['title' => $logValue->points]);
                    }
                    if ($logValue->is_retroactive) {
                        $events[] = array_merge($eventBase, ['title' => Locale::trans('attendance.retroactive_event_text', [], null), 'display' => 'list-item']);
                    }
                } elseif ($value <= $today && Carbon::instance($value)->diffInDays($today, true) <= Attendance::MAX_RETROACTIVE_DAYS) {
                    $events[] = array_merge($eventBase, ['groupId' => 'to_do', 'display' => 'list-item']);
                }
            }
        }

        $lang = Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), false);
        $localesMap = ['en' => null];
        $localeJs = $localesMap[$lang] ?? null;

        return [
            'today' => $today,
            'tomorrow' => $tomorrow,
            'end' => $end,
            'start' => $start,
            'attendance' => $attendance,
            'hasAttendedToday' => $hasAttendedToday,
            'todayCounts' => $todayCounts,
            'myRanking' => $myRanking,
            'logs' => $logs,
            'events' => $events,
            'validRange' => $validRange,
            'localeJs' => $localeJs,
        ];
    }
}
