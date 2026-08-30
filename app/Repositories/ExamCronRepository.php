<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Enums\ExamDiscovered;
use App\Enums\ExamFilterUser;
use App\Enums\ExamType;
use App\Enums\ExamUserStatus;
use App\Enums\UserDonate;
use App\Enums\UserEnabled;
use App\Enums\UserStatus;
use App\Models\BonusLogs;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Handles exam cron operations: assignment and checkout.
 *
 * Extracted from ExamRepository to reduce god-object surface area.
 */
class ExamCronRepository extends BaseRepository
{
    /** @return  mixed */
    public function cronjonAssign()
    {
        $examRepo = new ExamRepository;
        $exams = $examRepo->listValid(null, ExamDiscovered::YES->value, ExamType::EXAM->value);
        if ($exams->isEmpty()) {
            Logger::writeWithContext((string) 'No valid and discovered exam.', (string) 'info', (bool) false);

            return false;
        }
        /**
         * valid exam can has multiple
         *
         * @since 1.7.4
         */
        $result = 0;
        foreach ($exams as $exam) {
            $start = microtime(true);
            $count = $this->fetchUserAndDoAssign($exam);
            Logger::writeWithContext((string) sprintf('exam: %s assign to user count: %s -> %s, cost time: %s', $exam->id, gettype($count), $count, number_format(microtime(true) - $start, 3)), (string) 'info', (bool) false);
            $result += $count;
        }

        return $result;

    }

    public function fetchUserAndDoAssign(Exam $exam): bool|int
    {
        $examRepo = new ExamRepository;
        $progressRepo = new ExamProgressRepository;
        $filters = $exam->filters;
        Logger::writeWithContext((string) ("exam: {$exam->id}, filters: ".Json::encode($filters)), (string) 'info', (bool) false);
        $userTable = (new User)->getTable();
        $examUserTable = (new ExamUser)->getTable();
        // Fetch user doesn't has this exam and doesn't has any other unfinished exam
        $baseQuery = User::query()
            ->where("$userTable.enabled", UserEnabled::YES->value)
            ->where("$userTable.status", UserStatus::CONFIRMED->value)
            ->selectRaw("$userTable.*") // @phpstan-ignore argument.type
            ->orderBy("$userTable.id", 'asc');

        $filter = ExamFilterUser::USER_CLASS->value;
        if (! empty($filters[$filter])) {
            $baseQuery->whereIn("$userTable.class", $filters[$filter]);
        }

        $filter = ExamFilterUser::DONATE->value;
        if (! empty($filters[$filter]) && count($filters[$filter]) == 1) {
            $donateStatus = $filters[$filter][0];
            if ($donateStatus == UserDonate::YES->value) {
                $baseQuery->where(function (Builder $query) {
                    $query->where('donor', 'yes')->where(function (Builder $query) {
                        $query->whereNull('donoruntil')->orWhere('donoruntil', '>=', Carbon::now());
                    });
                });
            } elseif ($donateStatus == UserDonate::NO->value) {
                $baseQuery->where(function (Builder $query) {
                    $query->where('donor', 'no')->orWhere(function (Builder $query) {
                        $query->whereNotNull('donoruntil')->where('donoruntil', '<', Carbon::now());
                    });
                });
            } else {
                Logger::writeWithContext((string) "{$exam->id} filter {$filter}: {$donateStatus} invalid.", (string) 'error', (bool) false);

                return false;
            }
        }

        $filter = ExamFilterUser::REGISTER_TIME_RANGE->value;
        $range = $filters[$filter] ?? [];
        if (! empty($range)) {
            if (! empty($range[0])) {
                $baseQuery->where("$userTable.added", '>=', Carbon::parse($range[0])->toDateTimeString());
            }
            if (! empty($range[1])) {
                $baseQuery->where("$userTable.added", '<=', Carbon::parse($range[1])->toDateTimeString());
            }
        }

        $filter = ExamFilterUser::REGISTER_DAYS_RANGE->value;
        $range = $filters[$filter] ?? [];
        if (! empty($range)) {
            if (! empty($range[0])) {
                $baseQuery->where("$userTable.added", '<=', now()->subDays($range[0])->toDateTimeString());
            }
            if (! empty($range[1])) {
                $baseQuery->where("$userTable.added", '>=', now()->subDays($range[1])->toDateTimeString());
            }
        }

        // Does not has this exam
        $baseQuery->whereDoesntHave('exams', function (Builder $query) use ($exam) {
            $query->where('exam_id', $exam->id);
        });
        // Does not has any other normal exam
        $baseQuery->whereDoesntHave('exams', function (Builder $query) {
            $query->where('status', ExamUserStatus::NORMAL->value);
        });

        $size = 1000;
        $minId = 0;
        $result = 0;
        $begin = $exam->getBeginForUser();
        $end = $exam->getEndForUser();
        while (true) {
            $logPrefix = sprintf('[%s], exam: %s, size: %s', __FUNCTION__, $exam->id, $size);
            $users = (clone $baseQuery)->where("$userTable.id", '>', $minId)->limit($size)->get();
            Logger::writeWithContext((string) ("{$logPrefix}, query: ".LegacyDb::lastQuery(false, 'json').', counts: '.$users->count()), (string) 'info', (bool) false);
            if ($users->isEmpty()) {
                Logger::writeWithContext((string) 'no more data...', (string) 'info', (bool) false);
                break;
            }
            $now = Carbon::now()->toDateTimeString();
            foreach ($users as $user) {
                $minId = $user->id;
                $currentLogPrefix = sprintf("$logPrefix, user: %s", $user->id);
                $insert = [
                    'uid' => $user->id,
                    'exam_id' => $exam->id,
                    'begin' => $begin,
                    'end' => $end,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                Logger::writeWithContext((string) "{$currentLogPrefix}, exam will be assigned to this user.", (string) 'info', (bool) false);
                $examUser = ExamUser::query()->create($insert);
                $examUser->load('progresses');
                $progressRepo->updateProgress($examUser, $user);
                $result++;
            }
        }

        return $result;
    }

    /** @param  mixed  $ignoreTimeRange */
    public function cronjobCheckout($ignoreTimeRange = false): int
    {
        $examRepo = new ExamRepository;
        $progressRepo = new ExamProgressRepository;
        $now = Carbon::now(); // 保持 Carbon 对象即可，Laravel 会自动序列化
        $examUserTable = (new ExamUser)->getTable();
        $examTable = (new Exam)->getTable();
        $userTable = (new User)->getTable();

        $baseQuery = ExamUser::query()
            ->join($examTable, "$examUserTable.exam_id", '=', "$examTable.id")
            ->where("$examUserTable.status", ExamUserStatus::NORMAL->value)
            ->select("$examUserTable.*") // 替换 selectRaw
            ->with(['exam', 'user', 'user.language', 'progresses'])
            ->orderBy("$examUserTable.id", 'asc');

        if (! $ignoreTimeRange) {
            $baseQuery->where(function ($query) use ($examUserTable, $examTable, $now) {
                $query->where(function ($q) use ($examUserTable, $now) {
                    // 条件 1: exam_user.end 不为空且小于当前时间
                    $q->whereNotNull("$examUserTable.end")
                        ->where("$examUserTable.end", '<', $now);
                })
                    ->orWhere(function ($q) use ($examTable, $now) {
                        // 条件 2: exam.end 不为空且小于当前时间
                        $q->whereNotNull("$examTable.end")
                            ->where("$examTable.end", '<', $now);
                    })
                    ->orWhere(function ($q) use ($examUserTable, $examTable, $now) {
                        // 条件 3: exam.duration > 0 且过期
                        // 因为涉及到列与列的计算，这里需要用 whereRaw，但我们可以针对多数据库做自适应
                        $q->where("$examTable.duration", '>', 0);

                        if (DB::connection()->getDriverName() === 'pgsql') {
                            // PG 写法：使用 || 拼接字符串再转为 INTERVAL
                            $q->whereRaw("$examUserTable.created_at + ($examTable.duration || ' day')::INTERVAL < ?", [$now]); // @phpstan-ignore argument.type
                        } else {
                            // MySQL 写法
                            $q->whereRaw("DATE_ADD($examUserTable.created_at, INTERVAL $examTable.duration DAY) < ?", [$now]); // @phpstan-ignore argument.type
                        }
                    });
            });
        }

        $size = 1000;
        $minId = 0;
        $result = 0;

        while (true) {
            $logPrefix = sprintf('[%s], size: %s', __FUNCTION__, $size);
            $examUsers = (clone $baseQuery)->where("$examUserTable.id", '>', $minId)->limit($size)->get();
            Logger::writeWithContext((string) ("{$logPrefix}, fetch exam users: {$examUsers->count()} by: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
            if ($examUsers->isEmpty()) {
                Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            $result += $examUsers->count();
            $now = Carbon::now()->toDateTimeString();
            $examUserIdArr = $uidToDisable = $messageToSend = $userBanLog = [];
            $bonusLog = $uidToUpdateBonusPass = $uidToUpdateBonusFail = [];
            $successRewardBonus = $failDeductBonus = 0;
            $examUserToInsert = [];
            $userModifyLogs = [];
            foreach ($examUsers as $examUser) {
                $minId = $examUser->id;
                $examUserIdArr[] = $examUser->id;
                $uid = $examUser->uid;
                Cache::clearInboxCount($uid);
                /** @var Exam $exam */
                $exam = $examUser->exam;
                $successRewardBonus = (int) $exam->success_reward_bonus;
                $failDeductBonus = (int) $exam->fail_deduct_bonus;
                $currentLogPrefix = sprintf("$logPrefix, user: %s, exam: %s, examUser: %s", $uid, $examUser->exam_id, $examUser->id);
                if (! $examUser->user) {
                    Logger::writeWithContext((string) "{$currentLogPrefix}, user not exists, remove it!", (string) 'error', (bool) false);
                    $examUser->progresses()->delete();
                    $examUser->delete();

                    continue;
                }
                // update to the newest progress
                $examUser = $progressRepo->updateProgress($examUser, $examUser->user);
                if (! $examUser instanceof ExamUser) {
                    continue;
                }
                $locale = $examUser->user->locale;
                if ($examUser->is_done) {
                    Logger::writeWithContext((string) "{$currentLogPrefix}, [is_done]", (string) 'info', (bool) false);
                    $subjectTransKey = $exam->getMessageSubjectTransKey('pass');
                    $msgTransKey = $exam->getMessageContentTransKey('pass');
                    if ($exam->isTypeExam()) {
                        if (! empty($exam->recurring) && $examRepo->isExamMatchUser($exam, $examUser->user)) {
                            $examUserToInsert[] = [
                                'uid' => $examUser->user->id,
                                'exam_id' => $exam->id,
                                'begin' => $exam->getBeginForUser(),
                                'end' => $exam->getEndForUser(),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    } elseif ($exam->isTypeTask()) {
                        // reward bonus
                        if ($exam->success_reward_bonus > 0) {
                            $bonusLog[] = [
                                'uid' => $uid,
                                'old_total_value' => $examUser->user->seedbonus,
                                'value' => $exam->success_reward_bonus,
                                'new_total_value' => $examUser->user->seedbonus + $exam->success_reward_bonus,
                                'business_type' => BusinessType::TASK_PASS_REWARD->value,
                            ];
                            $uidToUpdateBonusPass[] = $uid;
                        }
                    }
                } else {
                    Logger::writeWithContext((string) "{$currentLogPrefix}, [not_done]", (string) 'info', (bool) false);
                    $subjectTransKey = $exam->getMessageSubjectTransKey('not_pass');
                    $msgTransKey = $exam->getMessageContentTransKey('not_pass');
                    if ($exam->isTypeExam()) {
                        // ban user
                        Logger::writeWithContext((string) "{$currentLogPrefix}, [will be banned]", (string) 'info', (bool) false);
                        $clearUser = $examUser->user;
                        if ($clearUser instanceof User) {
                            Cache::clearUser($clearUser->id, (string) $clearUser->passkey);
                        }
                        $uidToDisable[] = $uid;
                        $userModcomment = Locale::trans('exam.ban_user_modcomment', ['exam_name' => $exam->name, 'begin' => $examUser->begin, 'end' => $examUser->end], $locale);
                        $userModifyLogs[] = [
                            'user_id' => $uid,
                            'content' => $userModcomment,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $banLogReason = Locale::trans('exam.ban_log_reason', ['exam_name' => $exam->name, 'begin' => $examUser->begin, 'end' => $examUser->end], $locale);
                        $userBanLog[] = [
                            'uid' => $uid,
                            'username' => $examUser->user->username,
                            'reason' => $banLogReason,
                        ];
                    } elseif ($exam->isTypeTask()) {
                        // deduct bonus
                        if ($exam->fail_deduct_bonus > 0) {
                            $bonusLog[] = [
                                'uid' => $uid,
                                'old_total_value' => $examUser->user->seedbonus,
                                'value' => -1 * $exam->fail_deduct_bonus,
                                'new_total_value' => $examUser->user->seedbonus - $exam->fail_deduct_bonus,
                                'business_type' => BusinessType::TASK_NOT_PASS_DEDUCT->value,
                            ];
                            $uidToUpdateBonusFail[] = $uid;
                        }
                    }
                }
                $subject = Locale::trans($subjectTransKey, [], $locale);
                $msg = Locale::trans($msgTransKey, ['exam_name' => $exam->name, 'begin' => $examUser->begin, 'end' => $examUser->end, 'success_reward_bonus' => $exam->success_reward_bonus, 'fail_deduct_bonus' => $exam->fail_deduct_bonus], $locale);
                $messageToSend[] = [
                    'receiver' => $uid,
                    'added' => $now,
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }
            DB::transaction(function () use ($uidToDisable, $messageToSend, $examUserIdArr, $examUserToInsert, $userBanLog, $userModifyLogs, $bonusLog, $uidToUpdateBonusPass, $uidToUpdateBonusFail, $userTable, $successRewardBonus, $failDeductBonus, $logPrefix) {
                ExamUser::query()->whereIn('id', $examUserIdArr)->update(['status' => ExamUserStatus::FINISHED->value]);
                do {
                    $deleted = ExamProgress::query()->whereIn('exam_user_id', $examUserIdArr)->limit(10000)->delete();
                    Logger::writeWithContext((string) "{$logPrefix}, [DELETE_EXAM_PROGRESS], deleted: {$deleted}", (string) 'info', (bool) false);
                } while ($deleted > 0);
                Message::query()->insert($messageToSend);
                if (! empty($uidToDisable)) {
                    $updateResult = DB::table($userTable)->whereIn('id', $uidToDisable)->update(['enabled' => UserEnabled::NO->value]);
                    Logger::writeWithContext((string) sprintf("{$logPrefix}, disable %s users: %s, updateResult: %s", count($uidToDisable), implode(', ', $uidToDisable), $updateResult), (string) 'info', (bool) false);
                }
                if (! empty($userBanLog)) {
                    UserBanLog::query()->insert($userBanLog);
                }
                if (! empty($examUserToInsert)) {
                    ExamUser::query()->insert($examUserToInsert);
                }
                if (! empty($uidToUpdateBonusPass)) {
                    $updateResult = DB::table($userTable)->whereIn('id', $uidToUpdateBonusPass)->increment('seedbonus', $successRewardBonus);
                    Logger::writeWithContext((string) sprintf("{$logPrefix}, reward %s users: %s seedbonus, updateResult: %s", count($uidToUpdateBonusPass), implode(', ', $uidToUpdateBonusPass), $updateResult), (string) 'info', (bool) false);
                }
                if (! empty($uidToUpdateBonusFail)) {
                    $updateResult = DB::table($userTable)->whereIn('id', $uidToUpdateBonusFail)->decrement('seedbonus', $failDeductBonus);
                    Logger::writeWithContext((string) sprintf("{$logPrefix}, deduct %s users: %s seedbonus, updateResult: %s", count($uidToUpdateBonusFail), implode(', ', $uidToUpdateBonusFail), $updateResult), (string) 'info', (bool) false);
                }
                if (! empty($bonusLog)) {
                    BonusLogs::query()->insert($bonusLog);
                }
                if (! empty($userModifyLogs)) {
                    UserModifyLog::query()->insert($userModifyLogs);
                }
            });
        }

        return $result;
    }
}
