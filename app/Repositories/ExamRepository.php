<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Exam definition repository: CRUD, listing, and user matching.
 *
 * Assignment, progress, and cron logic has been extracted to:
 *
 * @see ExamUserRepository
 * @see ExamProgressRepository
 * @see ExamCronRepository
 */
class ExamRepository extends BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = Exam::query();
        $query->orderBy('priority', 'desc')->orderBy('id', 'asc');

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function store(array $params): Exam
    {
        $diffInHours = $this->checkBeginEnd($params);
        $this->checkIndexes($params, $diffInHours);
        $this->checkFilters($params);
        $formatted = $this->formatParams($params);
        /** @var array<string, mixed> $data */
        $data = $formatted;
        $exam = Exam::query()->create($data);

        return $exam;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function update(array $params, int $id): Exam
    {
        $diffInHours = $this->checkBeginEnd($params);
        $this->checkIndexes($params, $diffInHours);
        $this->checkFilters($params);
        $exam = Exam::query()->findOrFail($id);
        $formatted = $this->formatParams($params);
        /** @var array<string, mixed> $data */
        $data = $formatted;
        $exam->update($data);

        return $exam;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    private function formatParams(array $params): array
    {
        if (isset($params['begin']) && $params['begin'] == '') {
            $params['begin'] = null;
        }
        if (isset($params['end']) && $params['end'] == '') {
            $params['end'] = null;
        }
        $params['priority'] = intval($params['priority'] ?? 0);

        return $params;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    private function checkIndexes(array $params, float $examDuration): bool
    {
        if (empty($params['indexes'])) {
            throw new \InvalidArgumentException('Require index.');
        }
        $validIndex = [];
        foreach ($params['indexes'] as $index) {
            if (isset($index['checked']) && ! $index['checked']) {
                continue;
            }
            if (isset($validIndex[$index['index']])) {
                throw new \InvalidArgumentException(Locale::trans('admin.resources.exam.index_duplicate', ['index' => Locale::trans("exam.index_text_{$index['index']}", [], null)], null));
            }
            if (isset($index['require_value']) && ! ctype_digit((string) $index['require_value'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid require value for index: %s.',
                    $index['index']
                ));
            }
            if ($index['index'] == ExamIndex::SEED_TIME_AVERAGE->value) {
                if ($index['require_value'] > $examDuration) {
                    throw new \InvalidArgumentException(Locale::trans('admin.resources.exam.index_seed_time_average_require_value_invalid', ['index_seed_time_average_require_value' => $index['require_value'], 'duration' => $examDuration], null));
                }
            }
            $validIndex[$index['index']] = $index;
        }
        if (empty($validIndex)) {
            throw new \InvalidArgumentException('Require valid index.');
        }

        return true;
    }

    /**
     * check if begin/end valid, if yes, return diff in hours, else throw InvalidArgumentException
     *
     * @param  array<int|string, mixed>  $params
     */
    private function checkBeginEnd(array $params): float
    {
        if (
            ! empty($params['begin']) && ! empty($params['end'])
            && empty($params['duration'])
            && empty($params['recurring'])
        ) {
            $begin = Carbon::parse($params['begin']);
            $end = Carbon::parse($params['end']);

            return round($begin->diffInHours($end, true));
        }
        if (
            empty($params['begin']) && empty($params['end'])
            && isset($params['duration']) && ctype_digit((string) $params['duration']) && $params['duration'] > 0
            && empty($params['recurring'])
        ) {
            // unit: day
            return round(floatval($params['duration']) * 24);
        }
        if (
            empty($params['begin']) && empty($params['end'])
            && empty($params['duration'])
            && ! empty($params['recurring'])
        ) {
            $exam = new Exam(['recurring' => $params['recurring']]);
            $now = Carbon::now();
            $begin = $exam->getRecurringBegin($now);
            $end = $exam->getRecurringEnd($now);

            return round($begin->diffInHours($end, true));
        }

        throw new \InvalidArgumentException(Locale::trans('exam.time_condition_invalid', [], null));
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    private function checkFilters(array $params)
    {
        $filters = $params['filters'];
        $hasValid = false;

        $filter = ExamFilterUser::USER_CLASS->value;
        if (! empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$classes));
            if (! empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user class: %s', json_encode($diff)));
            }
        }

        $filter = ExamFilterUser::DONATE->value;
        if (! empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$donateStatus));
            if (! empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user donate status: %s', json_encode($diff)));
            }
        }

        $filter = ExamFilterUser::REGISTER_TIME_RANGE->value;
        $begin = $filters[$filter][0] ?? null;
        $end = $filters[$filter][1] ?? null;
        if ($begin) {
            if (strtotime($begin)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time begin: $begin");
            }
        }
        if ($end) {
            if (strtotime($end)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time end: $end");
            }
        }
        if ($begin && $end && $begin > $end) {
            throw new \InvalidArgumentException('user register time begin must less than end');
        }

        $filter = ExamFilterUser::REGISTER_DAYS_RANGE->value;
        $begin = $filters[$filter][0] ?? null;
        $end = $filters[$filter][1] ?? null;
        if ($begin) {
            if (is_numeric($begin) && $begin >= 0) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register days begin: $begin");
            }
        }
        if ($end) {
            if (is_numeric($end) && $end >= 0) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register days end: $end");
            }
        }
        if ($begin && $end && $begin > $end) {
            throw new \InvalidArgumentException('user register days begin must less than end');
        }

        if (! $hasValid) {
            throw new \InvalidArgumentException('No valid filters');
        }

        return true;
    }

    public function getDetail(int $id): Exam
    {
        $exam = Exam::query()->findOrFail($id);

        return $exam;
    }

    /**
     * delete an exam task, also will delete all exam user and progress.
     *
     * @return bool
     */
    public function delete(int $id)
    {
        $exam = Exam::query()->findOrFail($id);
        DB::transaction(function () use ($exam) {
            do {
                $deleted = ExamUser::query()->where('exam_id', $exam->id)->limit(10000)->delete();
            } while ($deleted > 0);
            do {
                $deleted = ExamProgress::query()->where('exam_id', $exam->id)->limit(10000)->delete();
            } while ($deleted > 0);
            $exam->delete();
        });

        return true;
    }

    /** @return  mixed */
    public function listIndexes()
    {
        $out = [];
        foreach (Exam::$indexes as $key => $value) {
            $value['index'] = $key;
            $out[] = $value;
        }

        return $out;
    }

    /**
     * list valid exams
     *
     * @param  mixed  $excludeId
     * @param  mixed  $isDiscovered
     * @param  mixed  $type
     * @return \Illuminate\Database\Eloquent\Collection<int, Exam>
     */
    public function listValid($excludeId = null, $isDiscovered = null, $type = null)
    {
        $now = Carbon::now();
        $query = Exam::query()
            ->where('status', ExamStatus::ENABLED->value)
            ->where(function ($q) use ($now) {
                $q->where(function ($sub) use ($now) {
                    // 如果 begin 和 end 都不为空，则判断时间
                    $sub->whereNotNull('begin')
                        ->whereNotNull('end')
                        ->where('begin', '<=', $now)
                        ->where('end', '>=', $now);
                })->orWhere(function ($sub) {
                    // 如果不满足上面的条件（即 begin 或 end 任意一个为空）
                    $sub->where(function ($inner) {
                        $inner->whereNull('begin')
                            ->orWhereNull('end');
                    })->where(function ($inner) {
                        $inner->where('duration', '>', 0)
                            ->orWhereNotNull('recurring');
                    });
                });
            });

        if (! is_null($excludeId)) {
            $excludeIds = is_array($excludeId) ? $excludeId : [$excludeId];
            $query->whereNotIn('id', $excludeIds);
        }
        if (! is_null($isDiscovered)) {
            $query->where('is_discovered', $isDiscovered);
        }
        if (! is_null($type)) {
            $query->where('type', $type);
        }

        return $query->orderBy('priority', 'desc')->orderBy('id', 'asc')->get();
    }

    /**
     * list user match exams
     *
     * @return Collection<int, Exam>
     */
    public function listMatchExam(int $uid)
    {
        $exams = $this->listValid(null, null, ExamType::EXAM->value);

        return $this->filterForUser($exams, $uid);
    }

    /**
     * @return Collection<int, Exam>
     */
    public function listMatchTask(int $uid)
    {
        $exams = $this->listValid(null, null, ExamType::TASK->value);

        return $this->filterForUser($exams, $uid);
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @return Collection<int, Exam>
     */
    private function filterForUser(Collection $exams, int $uid): Collection
    {
        $userInfo = User::query()->findOrFail($uid, User::$commonFields);

        return $exams->filter(function (Exam $exam) use ($userInfo) {
            return $this->isExamMatchUser($exam, $userInfo);
        });
    }

    public function isExamMatchUser(Exam $exam, User|int $user): bool
    {
        if (! $user instanceof User) {
            $user = User::query()->findOrFail(intval($user), ['id', 'username', 'added', 'class']);
        }
        $logPrefix = sprintf('exam: %s, user: %s', $exam->id, $user->id);
        $filters = $exam->filters;

        $filter = ExamFilterUser::USER_CLASS->value;
        $filterValues = $filters[$filter] ?? [];
        if (! empty($filterValues) && ! in_array($user->class, $filterValues)) {
            Logger::writeWithContext((string) ("{$logPrefix}, user class: {$user->class} not in: ".json_encode($filterValues)), (string) 'info', (bool) false);

            return false;
        }

        $filter = ExamFilterUser::DONATE->value;
        $filterValues = $filters[$filter] ?? [];
        if (! empty($filterValues) && ! in_array($user->donate_status, $filterValues)) {
            Logger::writeWithContext((string) ("{$logPrefix}, user donate status: {$user->donate_status} not in: ".json_encode($filterValues)), (string) 'info', (bool) false);

            return false;
        }

        $filter = ExamFilterUser::REGISTER_TIME_RANGE->value;
        $filterValues = $filters[$filter] ?? [];
        $added = Carbon::parse($user->added)->toDateTimeString();
        $registerTimeBegin = isset($filterValues[0]) ? Carbon::parse($filterValues[0])->toDateTimeString() : '';
        $registerTimeEnd = isset($filterValues[1]) ? Carbon::parse($filterValues[1])->toDateTimeString() : '';
        if (! empty($registerTimeBegin) && $added < $registerTimeBegin) {
            Logger::writeWithContext((string) ("{$logPrefix}, user added: {$added} not bigger than begin: ".$registerTimeBegin), (string) 'info', (bool) false);

            return false;
        }
        if (! empty($registerTimeEnd) && $added > $registerTimeEnd) {
            Logger::writeWithContext((string) ("{$logPrefix}, user added: {$added} not less than end: ".$registerTimeEnd), (string) 'info', (bool) false);

            return false;
        }

        $filter = ExamFilterUser::REGISTER_DAYS_RANGE->value;
        $filterValues = $filters[$filter] ?? [];
        $value = Carbon::parse($user->added)->diffInDays(now(), true);
        $begin = $filterValues[0] ?? null;
        $end = $filterValues[1] ?? null;
        if ($begin !== null && $value < $begin) {
            Logger::writeWithContext((string) ("{$logPrefix}, user registerDays: {$value} not bigger than begin: ".$begin), (string) 'info', (bool) false);

            return false;
        }
        if ($end !== null && $value > $end) {
            Logger::writeWithContext((string) ("{$logPrefix}, user registerDays: {$value} not less than end: ".$end), (string) 'info', (bool) false);

            return false;
        }

        try {
            $user->checkIsNormal();

            return true;
        } catch (\Throwable $throwable) {
            Logger::writeWithContext((string) ("{$logPrefix}, user is not normal: ".$throwable->getMessage()), (string) 'info', (bool) false);

            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Delegating methods — backward compatibility for callers not yet updated
    //  to use ExamUserRepository, ExamProgressRepository, or ExamCronRepository
    //  directly.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  mixed  $begin
     * @param  mixed  $end
     * @return mixed
     */
    public function assignToUser(int $uid, int $examId, $begin = null, $end = null)
    {
        return (new ExamUserRepository)->assignToUser($uid, $examId, $begin, $end);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function listUser(array $params)
    {
        return (new ExamUserRepository)->listUser($params);
    }

    /**
     * @return mixed
     */
    public function removeExamUser(int $examUserId)
    {
        return (new ExamUserRepository)->removeExamUser($examUserId);
    }

    /**
     * @return mixed
     */
    public function avoidExamUser(int $examUserId)
    {
        return (new ExamUserRepository)->avoidExamUser($examUserId);
    }

    public function updateExamUserEnd(ExamUser $examUser, Carbon $end, string $reason = ''): void
    {
        (new ExamUserRepository)->updateExamUserEnd($examUser, $end, $reason);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function removeExamUserBulk(array $params, User $user)
    {
        return (new ExamUserRepository)->removeExamUserBulk($params, $user);
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function avoidExamUserBulk(array $params, User $user): int
    {
        return (new ExamUserRepository)->avoidExamUserBulk($params, $user);
    }

    /**
     * @return mixed
     */
    public function recoverExamUser(int $examUserId)
    {
        return (new ExamUserRepository)->recoverExamUser($examUserId);
    }

    /**
     * @param  array<int|string, mixed>  $indexAndValue
     * @return bool
     *
     * @deprecated old version used
     */
    public function addProgress(int $uid, int $torrentId, array $indexAndValue)
    {
        return (new ExamProgressRepository)->addProgress($uid, $torrentId, $indexAndValue);
    }

    /**
     * @param  mixed  $examUser
     */
    public function updateProgress($examUser, ?User $user = null): ExamUser|bool
    {
        return (new ExamProgressRepository)->updateProgress($examUser, $user);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $status
     * @return mixed|null
     */
    public function getUserExamProgress($uid, $status = null)
    {
        return (new ExamProgressRepository)->getUserExamProgress($uid, $status);
    }

    /**
     * @return array<int|string, mixed>|null
     *
     * @deprecated
     */
    public function calculateProgress(ExamUser $examUser, bool $allSum = false)
    {
        return (new ExamProgressRepository)->calculateProgress($examUser, $allSum);
    }

    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return mixed
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        return (new ExamProgressRepository)->getProgressFormatted($exam, $progress, $locale);
    }

    /** @return  array<int|string, mixed> */
    public function updateProgressBulk(): array
    {
        return (new ExamProgressRepository)->updateProgressBulk();
    }

    /** @return  mixed */
    public function cronjonAssign()
    {
        return (new ExamCronRepository)->cronjonAssign();
    }

    public function fetchUserAndDoAssign(Exam $exam): bool|int
    {
        return (new ExamCronRepository)->fetchUserAndDoAssign($exam);
    }

    /** @param  mixed  $ignoreTimeRange */
    public function cronjobCheckout($ignoreTimeRange = false): int
    {
        return (new ExamCronRepository)->cronjobCheckout($ignoreTimeRange);
    }
}
