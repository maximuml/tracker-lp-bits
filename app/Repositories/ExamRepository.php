<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Enums\ExamDiscovered;
use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Enums\UserDonate;
use App\Enums\UserEnabled;
use App\Enums\UserStatus;
use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Env;
use App\Support\Format;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                    'Invalid require value for index: %s.', $index['index']
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

    private function isExamMatchUser(Exam $exam, User|int $user): bool
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

    /**
     * assign exam to user
     *
     * @param  mixed  $begin
     * @param  mixed  $end
     * @return mixed
     */
    public function assignToUser(int $uid, int $examId, $begin = null, $end = null)
    {
        $logPrefix = "uid: $uid, examId: $examId, begin: $begin, end: $end";
        /** @var Exam $exam */
        $exam = Exam::query()->find($examId);
        $user = User::query()->findOrFail($uid);
        $locale = $user->locale;
        $authUserClass = UserDisplay::currentClass();
        $authUserId = UserDisplay::currentId();
        $now = Carbon::now();
        if (! empty($exam->begin)) {
            $specificBegin = Carbon::parse($exam->begin);
            if ($specificBegin->isAfter($now)) {
                throw new NexusException(Locale::trans('exam.not_between_begin_end_time', [], $locale));
            }
        }
        if (! empty($exam->end)) {
            $specificEnd = Carbon::parse($exam->end);
            if ($specificEnd->isBefore($now)) {
                throw new NexusException(Locale::trans('exam.not_between_begin_end_time', [], $locale));
            }
        }
        if ($exam->isTypeExam()) {
            if ($authUserClass <= $user->class) {
                // exam only can assign by upper class admin
                throw new NexusException(Locale::trans('nexus.no_permission', [], $locale));
            }
        } elseif ($exam->isTypeTask()) {
            if ($user->id != $authUserId) {
                // task only can be claimed by self
                throw new NexusException(Locale::trans('exam.claim_by_yourself_only', [], $locale));
            }
            if ($exam->max_user_count > 0) {
                $claimUserCount = $exam->onGoingUsers()->count();
                if ($claimUserCount >= $exam->max_user_count) {
                    throw new NexusException(Locale::trans('exam.reach_max_user_count', [], $locale));
                }
            }
        }

        if (! $this->isExamMatchUser($exam, $user)) {
            throw new NexusException(Locale::trans('exam.not_match_target_user', [], $locale));
        }
        if ($user->exams()->where('status', ExamUserStatus::NORMAL->value)->exists()) {
            throw new NexusException(Locale::trans('exam.has_other_on_the_way', ['type_text' => $exam->typeText], $locale));
        }
        $exists = ExamUser::query()
            ->where('uid', $uid)
            ->where('exam_id', $exam->id)
            ->where('status', ExamUserStatus::NORMAL->value)
            ->exists();
        if ($exists) {
            throw new NexusException(Locale::trans('exam.claimed_already', [], $locale));
        }
        $data = [
            'exam_id' => $exam->id,
        ];
        if (empty($begin)) {
            $begin = $exam->getBeginForUser();
        } else {
            $begin = Carbon::parse($begin);
        }
        if (empty($end)) {
            $end = $exam->getEndForUser();
        } else {
            $end = Carbon::parse($end);
        }
        $data['begin'] = $begin;
        $data['end'] = $end;
        Logger::writeWithContext((string) ("{$logPrefix}, data: ".Json::encode($data)), (string) 'info', (bool) false);
        $examUser = $user->exams()->create($data);
        $this->updateProgress($examUser, $user);

        return $examUser;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function listUser(array $params)
    {
        $query = ExamUser::query();
        if (! empty($params['uid'])) {
            $query->where('uid', $params['uid']);
        }
        if (! empty($params['exam_id'])) {
            $query->where('exam_id', $params['exam_id']);
        }
        if (isset($params['is_done']) && is_numeric($params['is_done'])) {
            $query->where('is_done', $params['is_done']);
        }
        if (isset($params['status']) && is_numeric($params['status'])) {
            $query->where('status', $params['status']);
        }
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        $result = $query->with(['user', 'exam'])->paginate();

        return $result;

    }

    /**
     * @param  array<int|string, mixed>  $indexAndValue
     * @return bool
     *
     * @deprecated old version used
     *
     * @throws NexusException
     */
    public function addProgress(int $uid, int $torrentId, array $indexAndValue)
    {
        $logPrefix = "uid: $uid, torrentId: $torrentId, indexAndValue: ".json_encode($indexAndValue);
        Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);

        $user = User::query()->findOrFail($uid);
        $user->checkIsNormal();

        $now = Carbon::now()->toDateTimeString();
        $examUser = $user->exams()->where('status', ExamUserStatus::NORMAL->value)->orderBy('id', 'desc')->first();
        if (! $examUser) {
            Logger::writeWithContext((string) ('no exam is on the way, '.LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);

            return false;
        }
        $exam = $examUser->exam;
        if (! $exam) {
            throw new NexusException("exam: {$examUser->exam_id} not exists.");
        }
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (! $begin || ! $end) {
            Logger::writeWithContext((string) sprintf('no begin or end, examUser: %s', $examUser->toJson()), (string) 'info', (bool) false);

            return false;
        }
        if ($now < $begin || $now > $end) {
            Logger::writeWithContext((string) sprintf('now: %s, not in exam time range: %s ~ %s', $now, $begin, $end), (string) 'info', (bool) false);

            return false;
        }
        $indexes = collect($exam->indexes)->keyBy('index');
        Logger::writeWithContext((string) ('examUser: '.$examUser->toJson().', indexes: '.$indexes->toJson()), (string) 'info', (bool) false);

        if (! isset($indexAndValue[ExamIndex::SEED_BONUS->value])) {
            // seed bonus is relative to user all torrents, not single one, torrentId = 0
            $torrentFields = ['id', 'visible', 'banned'];
            $torrent = Torrent::query()->findOrFail($torrentId, $torrentFields);
            $torrent->checkIsNormal($torrentFields);
        }

        $insert = [];
        foreach ($indexAndValue as $indexId => $value) {
            if (! $indexes->has($indexId)) {
                Logger::writeWithContext((string) sprintf('Exam: %s does not has index: %s.', $exam->id, $indexId), (string) 'info', (bool) false);

                continue;
            }
            $indexInfo = $indexes->get($indexId);
            if (! isset($indexInfo['checked']) || ! $indexInfo['checked']) {
                Logger::writeWithContext((string) sprintf('Exam: %s index: %s is not checked.', $exam->id, $indexId), (string) 'info', (bool) false);

                continue;
            }
            $insert[] = [
                'exam_user_id' => $examUser->id,
                'uid' => $user->id,
                'exam_id' => $exam->id,
                'torrent_id' => $torrentId,
                'index' => $indexId,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (empty($insert)) {
            Logger::writeWithContext((string) 'no progress to insert.', (string) 'info', (bool) false);

            return false;
        }
        ExamProgress::query()->insert($insert);
        Logger::writeWithContext((string) ('[addProgress] '.Json::encode($insert)), (string) 'info', (bool) false);

        /**
         * Updating progress is more performance intensive and will only be done with a certain probability
         */
        $probability = (int) Env::get('EXAM_PROGRESS_UPDATE_PROBABILITY', 60);
        $random = mt_rand(1, 100);
        Logger::writeWithContext((string) "probability: {$probability}, random: {$random}", (string) 'info', (bool) false);
        if ($random > $probability) {
            Logger::writeWithContext((string) "[SKIP_UPDATE_PROGRESS], random: {$random} > probability: {$probability}", (string) 'warning', (bool) false);

            return true;
        }
        $examProgress = $this->calculateProgress($examUser);
        if (! is_array($examProgress)) {
            $examProgress = [];
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $examProgress);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return ! $item['passed'];
        });
        $update = [
            'progress' => $examProgress,
            'is_done' => count($examNotPassed) ? ExamUserIsDone::NO->value : ExamUserIsDone::YES->value,
        ];
        Logger::writeWithContext((string) ('[updateProgress] '.Json::encode($update)), (string) 'info', (bool) false);
        $examUser->update($update);

        return true;
    }

    /**
     * in exam_progress table
     * old version: value is an increment
     * new version: both value and init_value are cumulative, increment = value - init_value
     * in exam_users table, progress field always is increment
     * old version: progress = sum(exam_progress.value)
     * new version：progress = exam_progress.value - exam_progress.init_value
     *
     * @param  mixed  $examUser
     */
    public function updateProgress($examUser, ?User $user = null): ExamUser|bool
    {
        $beginTimestamp = microtime(true);
        if (! $examUser instanceof ExamUser) {
            $uid = intval($examUser);
            $examUser = ExamUser::query()
                ->where('uid', $uid)
                ->where('status', ExamUserStatus::NORMAL->value)
                ->first();
            if (! $examUser instanceof ExamUser) {
                Logger::writeWithContext((string) "user: {$uid} no exam.", (string) 'info', (bool) false);

                return false;
            }
        }
        if ($examUser->status != ExamUserStatus::NORMAL->value) {
            Logger::writeWithContext((string) "examUser: {$examUser->id} status not normal, won't update progress.", (string) 'info', (bool) false);

            return false;
        }
        if ($examUser->is_done == ExamUserIsDone::YES->value) {
            /**
             * continue  update
             *
             * @since v1.7.0
             */
        }
        $exam = $examUser->exam;
        if (! $user instanceof User) {
            $user = $examUser->user()->select(['id', 'uploaded', 'downloaded', 'seedtime', 'leechtime', 'seedbonus', 'seed_points'])->first();
        }
        if (! $user instanceof User) {
            throw new \InvalidArgumentException("examUser: {$examUser->id} no user.");
        }
        if (! $exam instanceof Exam) {
            throw new \InvalidArgumentException("examUser: {$examUser->id} no exam.");
        }
        $attributes = [
            'exam_user_id' => $examUser->id,
            'uid' => $user->id,
            'exam_id' => $exam->id,
        ];
        $logPrefix = json_encode($attributes);
        $begin = $examUser->begin;
        if (empty($begin)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no begin.");
        }
        $end = $examUser->end;
        if (empty($end)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no end.");
        }
        $progressGrouped = $examUser->progresses->keyBy('index');
        $examUserProgressFieldData = [];
        $now = now();
        foreach ($exam->indexes as $index) {
            if (! isset($index['checked']) || ! $index['checked']) {
                continue;
            }
            if ($progressGrouped->isNotEmpty() && ! $progressGrouped->has($index['index'])) {
                continue;
            }
            if (! isset(Exam::$indexes[$index['index']])) {
                $msg = "Unknown index: {$index['index']}";
                Logger::writeWithContext((string) "{$logPrefix}, {$msg}", (string) 'error', (bool) false);
                throw new \RuntimeException($msg);
            }
            Logger::writeWithContext((string) ("{$logPrefix}, [HANDLING INDEX {$index['index']}]: ".json_encode($index)), (string) 'info', (bool) false);
            // First, collect data to store/update in table: exam_progress
            $attributes['index'] = $index['index'];
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $attributes['value'] = $this->getProgressValue($user, $index['index'], $examUser);
            Logger::writeWithContext((string) ('[GET_TOTAL_VALUE]: '.$attributes['value']), (string) 'info', (bool) false);
            $newVersionProgress = ExamProgress::query()
                ->where('exam_user_id', $examUser->id)
                ->where('torrent_id', -1)
                ->where('index', $index['index'])
                ->orderBy('id', 'desc')
                ->first();
            Logger::writeWithContext((string) ('check newVersionProgress: '.LegacyDb::lastQuery(false, 'json').', exists: '.json_encode($newVersionProgress)), (string) 'info', (bool) false);
            if ($newVersionProgress) {
                // just need to do update the value
                if ($attributes['value'] != $newVersionProgress->value) {
                    $newVersionProgress->update(['value' => $attributes['value']]);
                    Logger::writeWithContext((string) ('newVersionProgress [EXISTS], doUpdate: '.LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                } else {
                    Logger::writeWithContext((string) 'newVersionProgress [EXISTS], no change....', (string) 'info', (bool) false);
                }
                $attributes['init_value'] = $newVersionProgress->init_value;
            } else {
                // do insert.
                $attributes['init_value'] = $attributes['value'];
                $attributes['torrent_id'] = -1;
                ExamProgress::query()->insert($attributes);
                Logger::writeWithContext((string) ('newVersionProgress [NOT EXISTS], doInsert with: '.json_encode($attributes)), (string) 'info', (bool) false);
            }

            // Second, update exam_user.progress
            if ($index['index'] == ExamIndex::SEED_TIME_AVERAGE->value) {
                $torrentCountsRes = Snatch::query()
                    ->where('userid', $user->id)
                    ->where('last_action', '>=', $begin)
                    ->where('last_action', '<=', $end)
                    ->selectRaw('count(distinct(torrentid)) as counts')
                    ->first();
                Logger::writeWithContext((string) ("special index: {$index['index']}, get torrent count by: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                // if just seeding, no download torrent, counts = 1
                if ($torrentCountsRes && $torrentCountsRes->counts > 0) {
                    $torrentCounts = $torrentCountsRes->counts;
                    Logger::writeWithContext((string) "torrent count: {$torrentCounts}", (string) 'info', (bool) false);
                } else {
                    $torrentCounts = 1;
                    Logger::writeWithContext((string) 'torrent count is 0, use 1', (string) 'info', (bool) false);
                }
                $examUserProgressFieldData[$index['index']] = bcdiv((string) bcsub($attributes['value'], $attributes['init_value']), (string) $torrentCounts);
                Logger::writeWithContext((string) sprintf('torrentCounts > 0, examUserProgress: (total(%s) - init_value(%s)) / %s = %s', $attributes['value'], $attributes['init_value'], $torrentCounts, $examUserProgressFieldData[$index['index']]), (string) 'info', (bool) false);
            } else {
                $examUserProgressFieldData[$index['index']] = bcsub($attributes['value'], $attributes['init_value']);
                Logger::writeWithContext((string) sprintf("normal index: {$index['index']}, examUserProgress: total(%s) - init_value(%s) = %s", $attributes['value'], $attributes['init_value'], $examUserProgressFieldData[$index['index']]), (string) 'info', (bool) false);
            }
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $examUserProgressFieldData);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return ! $item['passed'];
        });

        $update = [
            'progress' => $examUserProgressFieldData,
            'is_done' => count($examNotPassed) ? ExamUserIsDone::NO->value : ExamUserIsDone::YES->value,
        ];
        $result = $examUser->update($update);
        Logger::writeWithContext((string) sprintf('[UPDATE_PROGRESS] %s, result: %s, cost time: %s sec', json_encode($update), var_export($result, true), sprintf('%.3f', microtime(true) - $beginTimestamp)), (string) 'info', (bool) false);
        $examUser->progress_formatted = $examProgressFormatted;

        return $examUser;
    }

    /**
     * @return mixed
     */
    private function getProgressValue(User $user, int $index, ExamUser $examUser)
    {
        if ($index == ExamIndex::UPLOADED->value) {
            return $user->uploaded;
        }
        if ($index == ExamIndex::DOWNLOADED->value) {
            return $user->downloaded;
        }
        if ($index == ExamIndex::SEED_BONUS->value) {
            return $user->seedbonus;
        }
        if ($index == ExamIndex::SEED_TIME_AVERAGE->value) {
            return $user->seedtime;
        }
        if ($index == ExamIndex::SEED_POINTS->value) {
            return $user->seed_points;
        }
        if ($index == ExamIndex::UPLOAD_TORRENT_COUNT->value) {
            return Torrent::query()->where('owner', $user->id)->where('added', '>=', $examUser->created_at)->normal()->count();
        }
        throw new \InvalidArgumentException("Invalid index: $index");
    }

    /**
     * get user exam status
     *
     * @param  mixed  $uid
     * @param  mixed  $status
     * @return mixed|null
     */
    public function getUserExamProgress($uid, $status = null)
    {
        $logPrefix = "uid: $uid";
        $query = ExamUser::query()->where('uid', $uid)->orderBy('exam_id', 'desc');
        if (! is_null($status)) {
            $query->where('status', $status);
        }
        $examUsers = $query->get();
        if ($examUsers->isEmpty()) {
            Logger::writeWithContext((string) ("{$logPrefix}, no examUser, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);

            return null;
        }
        if ($examUsers->count() > 1) {
            Logger::writeWithContext((string) "{$logPrefix}, user exam more than 1.", (string) 'warning', (bool) false);
        }
        $examUser = $examUsers->first();
        $logPrefix .= ', examUser: '.$examUser->id;
        try {
            $updateResult = $this->updateProgress($examUser);
            if ($updateResult) {
                Logger::writeWithContext((string) "{$logPrefix}, [UPDATE_PROGRESS_SUCCESS_RETURN_DIRECTLY]", (string) 'info', (bool) false);

                return $updateResult;
            } else {
                Logger::writeWithContext((string) "{$logPrefix}, [UPDATE_PROGRESS_FAIL]", (string) 'info', (bool) false);
            }
        } catch (\Exception $exception) {
            Logger::writeWithContext((string) ("{$logPrefix}, [UPDATE_PROGRESS_FAIL]: ".$exception->getMessage()), (string) 'error', (bool) false);
        }
        $exam = $examUser->exam;
        $progress = $examUser->progress;
        Logger::writeWithContext((string) ("{$logPrefix}, progress: ".Json::encode($progress)), (string) 'info', (bool) false);
        $examUser->progress = $progress;
        $examUser->progress_formatted = $this->getProgressFormatted($exam, (array) $progress);

        return $examUser;
    }

    /**
     * @return array<int|string, mixed>|null
     *
     * @deprecated
     */
    public function calculateProgress(ExamUser $examUser, bool $allSum = false)
    {
        $logPrefix = 'examUser: '.$examUser->id;
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (! $begin) {
            Logger::writeWithContext((string) "{$logPrefix}, no begin", (string) 'info', (bool) false);

            return null;
        }
        if (! $end) {
            Logger::writeWithContext((string) "{$logPrefix}, no end", (string) 'info', (bool) false);

            return null;
        }
        $progressSum = $examUser->progresses()
            ->where('created_at', '>=', $begin)
            ->where('created_at', '<=', $end)
            ->selectRaw('`index`, sum(`value`) as sum')
            ->groupBy(['index'])
            ->get()
            ->pluck('sum', 'index')
            ->toArray();
        $logPrefix .= ', progressSum raw: '.json_encode($progressSum).', query: '.LegacyDb::lastQuery(false, 'json');
        if ($allSum) {
            Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);

            return $progressSum;
        }

        $index = ExamIndex::SEED_TIME_AVERAGE->value;
        if (isset($progressSum[$index])) {
            $torrentCountRow = $examUser->progresses()
                ->where('index', $index)
                ->where('torrent_id', '>=', 0)
                ->selectRaw('count(distinct(torrent_id)) as torrent_count')
                ->first();
            $torrentCount = $torrentCountRow instanceof ExamProgress ? (int) $torrentCountRow->torrent_count : 0;
            $progressSum[$index] = intval($progressSum[$index] / $torrentCount);
            $logPrefix .= ", index: INDEX_SEED_TIME_AVERAGE, get torrent count: $torrentCount, from query: ".LegacyDb::lastQuery(false, 'json');
        }

        Logger::writeWithContext((string) ("{$logPrefix}, final progressSum: ".json_encode($progressSum)), (string) 'info', (bool) false);

        return $progressSum;

    }

    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return mixed
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        $result = [];
        foreach ($exam->indexes as $key => $index) {
            if (! isset($index['checked']) || ! $index['checked']) {
                continue;
            }
            if (! isset($progress[$index['index']])) {
                continue;
            }
            $currentValue = $progress[$index['index']] ?? 0;
            $requireValue = $index['require_value'];
            $unit = Exam::$indexes[$index['index']]['unit'] ?? '';
            switch ($index['index']) {
                case ExamIndex::UPLOADED->value:
                case ExamIndex::DOWNLOADED->value:
                    $currentValueFormatted = Format::size($currentValue);
                    $requireValueAtomic = $requireValue * 1024 * 1024 * 1024;
                    break;
                case ExamIndex::SEED_TIME_AVERAGE->value:
                    $currentValueFormatted = number_format($currentValue / 3600, 2)." $unit";
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
            $index['name'] = Exam::$indexes[$index['index']]['name'] ?? '';
            $index['index_formatted'] = Locale::trans('exam.index_text_'.$index['index'], [], null);
            $index['require_value_formatted'] = "$requireValue $unit";
            $index['current_value'] = $currentValue;
            $index['current_value_formatted'] = $currentValueFormatted;
            $index['passed'] = $currentValue >= $requireValueAtomic;
            $index['index_result'] = $index['passed'] ? Locale::trans($exam->getPassResultTransKey('pass'), [], null) : Locale::trans($exam->getPassResultTransKey('not_pass'), [], null);
            $result[] = $index;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function removeExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->findOrFail($examUserId);
        $result = DB::transaction(function () use ($examUser) {
            do {
                $deleted = $examUser->progresses()->limit(10000)->delete();
            } while ($deleted > 0);

            return $examUser->delete();
        });

        return $result;
    }

    /**
     * @return mixed
     */
    public function avoidExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->where('status', ExamUserStatus::NORMAL->value)->findOrFail($examUserId);
        $result = $examUser->update(['status' => ExamUserStatus::AVOIDED->value]);

        return $result;
    }

    /**
     * @return mixed
     */
    public function updateExamUserEnd(ExamUser $examUser, Carbon $end, string $reason = '')
    {
        if ($end->isBefore($examUser->begin)) {
            throw new \InvalidArgumentException(Locale::trans('exam-user.end_can_not_before_begin', ['begin' => $examUser->begin, 'end' => $end], null));
        }
        if ($examUser->status != ExamUserStatus::NORMAL->value) {
            throw new \LogicException(Locale::trans('exam-user.status_not_allow_update_end', ['status_text' => Locale::trans('exam-user.status.'.ExamUserStatus::NORMAL->value, [], null)], null));
        }
        $oldEndTime = $examUser->end;
        $locale = $examUser->user->locale;
        $examName = $examUser->exam->name;
        Message::add([
            'sender' => 0,
            'receiver' => $examUser->uid,
            'added' => now(),
            'subject' => Locale::trans('message.exam_user_end_time_updated.subject', ['exam_name' => $examName], $locale),
            'msg' => Locale::trans('message.exam_user_end_time_updated.body', ['exam_name' => $examName, 'old_end_time' => $oldEndTime, 'new_end_time' => $end, 'operator' => UserDisplay::currentUsername(), 'reason' => $reason], $locale),
        ]);
        $examUser->update(['end' => $end]);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function removeExamUserBulk(array $params, User $user)
    {
        $result = $this->getExamUserBulkQuery($params)->delete();
        Logger::writeWithContext((string) sprintf('user: %s bulk delete by filter: %s, result: %s', $user->id, json_encode($params), json_encode($result)), (string) 'alert', (bool) false);

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function avoidExamUserBulk(array $params, User $user): int
    {
        $query = $this->getExamUserBulkQuery($params)->where('status', ExamUserStatus::NORMAL->value);
        $update = [
            'status' => ExamUserStatus::AVOIDED->value,
        ];
        $affected = $query->update($update);
        Logger::writeWithContext((string) sprintf('user: %s bulk avoid by filter: %s, affected: %s', $user->id, json_encode($params), $affected), (string) 'alert', (bool) false);

        return $affected;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return Builder<ExamUser>
     */
    private function getExamUserBulkQuery(array $params): Builder
    {
        $query = ExamUser::query();
        $hasWhere = false;
        $validFilter = ['uid', 'id', 'exam_id'];
        foreach ($validFilter as $item) {
            if (! empty($params[$item])) {
                $hasWhere = true;
                $query->whereIn($item, Arr::wrap($params[$item]));
            }
        }
        if (! $hasWhere) {
            throw new \InvalidArgumentException('No filter.');
        }

        return $query;
    }

    /**
     * @return mixed
     */
    public function recoverExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->where('status', ExamUserStatus::AVOIDED->value)->findOrFail($examUserId);
        $result = $examUser->update(['status' => ExamUserStatus::NORMAL->value]);

        return $result;
    }

    /** @return  mixed */
    public function cronjonAssign()
    {
        $exams = $this->listValid(null, ExamDiscovered::YES->value, ExamType::EXAM->value);
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
        $filters = $exam->filters;
        Logger::writeWithContext((string) ("exam: {$exam->id}, filters: ".Json::encode($filters)), (string) 'info', (bool) false);
        $userTable = (new User)->getTable();
        $examUserTable = (new ExamUser)->getTable();
        // Fetch user doesn't has this exam and doesn't has any other unfinished exam
        $baseQuery = User::query()
            ->where("$userTable.enabled", UserEnabled::YES->value)
            ->where("$userTable.status", UserStatus::CONFIRMED->value)
            ->selectRaw("$userTable.*")
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
                $this->updateProgress($examUser, $user);
                $result++;
            }
        }

        return $result;
    }

    /** @param  mixed  $ignoreTimeRange */
    public function cronjobCheckout($ignoreTimeRange = false): int
    {
        $now = Carbon::now(); // 保持 Carbon 对象即可，Laravel 会自动序列化
        $examUserTable = (new ExamUser)->getTable();
        $examTable = (new Exam)->getTable();
        $userTable = (new User)->getTable();

        $baseQuery = ExamUser::query()
            ->join($examTable, "$examUserTable.exam_id", '=', "$examTable.id")
            ->where("$examUserTable.status", ExamUserStatus::NORMAL->value)
            ->select("$examUserTable.*") // 替换 selectRaw
            ->with(['exam', 'user', 'user.language'])
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
                            $q->whereRaw("$examUserTable.created_at + ($examTable.duration || ' day')::INTERVAL < ?", [$now]);
                        } else {
                            // MySQL 写法
                            $q->whereRaw("DATE_ADD($examUserTable.created_at, INTERVAL $examTable.duration DAY) < ?", [$now]);
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
            $bonusLog = $userBonusUpdate = $uidToUpdateBonus = [];
            $examUserToInsert = [];
            $userModifyLogs = [];
            foreach ($examUsers as $examUser) {
                $minId = $examUser->id;
                $examUserIdArr[] = $examUser->id;
                $uid = $examUser->uid;
                Cache::clearInboxCount($uid);
                /** @var Exam $exam */
                $exam = $examUser->exam;
                $currentLogPrefix = sprintf("$logPrefix, user: %s, exam: %s, examUser: %s", $uid, $examUser->exam_id, $examUser->id);
                if (! $examUser->user) {
                    Logger::writeWithContext((string) "{$currentLogPrefix}, user not exists, remove it!", (string) 'error', (bool) false);
                    $examUser->progresses()->delete();
                    $examUser->delete();

                    continue;
                }
                // update to the newest progress
                $examUser = $this->updateProgress($examUser, $examUser->user);
                if (! $examUser instanceof ExamUser) {
                    continue;
                }
                $locale = $examUser->user->locale;
                if ($examUser->is_done) {
                    Logger::writeWithContext((string) "{$currentLogPrefix}, [is_done]", (string) 'info', (bool) false);
                    $subjectTransKey = $exam->getMessageSubjectTransKey('pass');
                    $msgTransKey = $exam->getMessageContentTransKey('pass');
                    if ($exam->isTypeExam()) {
                        if (! empty($exam->recurring) && $this->isExamMatchUser($exam, $examUser->user)) {
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
                            $uidToUpdateBonus[] = $uid;
                            $bonusLog[] = [
                                'uid' => $uid,
                                'old_total_value' => $examUser->user->seedbonus,
                                'value' => $exam->success_reward_bonus,
                                'new_total_value' => $examUser->user->seedbonus + $exam->success_reward_bonus,
                                'business_type' => BusinessType::TASK_PASS_REWARD->value,
                            ];
                            $userBonusUpdate[] = sprintf('when `id` = %s then seedbonus + %d', $uid, $exam->success_reward_bonus);
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
                            $uidToUpdateBonus[] = $uid;
                            $bonusLog[] = [
                                'uid' => $uid,
                                'old_total_value' => $examUser->user->seedbonus,
                                'value' => -1 * $exam->fail_deduct_bonus,
                                'new_total_value' => $examUser->user->seedbonus - $exam->fail_deduct_bonus,
                                'business_type' => BusinessType::TASK_NOT_PASS_DEDUCT->value,
                            ];
                            $userBonusUpdate[] = sprintf('when `id` = %s then seedbonus - %d', $uid, $exam->fail_deduct_bonus);
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
            DB::transaction(function () use ($uidToDisable, $messageToSend, $examUserIdArr, $examUserToInsert, $userBanLog, $userModifyLogs, $userBonusUpdate, $bonusLog, $uidToUpdateBonus, $userTable, $logPrefix) {
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
                if (! empty($userBonusUpdate)) {
                    $uidStr = implode(', ', $uidToUpdateBonus);
                    $sql = sprintf(
                        'update %s set seedbonus = case %s end where id in (%s)',
                        $userTable, implode(' ', $userBonusUpdate), $uidStr
                    );
                    $updateResult = DB::update($sql);
                    Logger::writeWithContext((string) sprintf("{$logPrefix}, update %s users: %s seedbonus, sql: %s, updateResult: %s", count($uidToUpdateBonus), $uidStr, $sql, $updateResult), (string) 'info', (bool) false);
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

    /** @return  array<int|string, mixed> */
    public function updateProgressBulk(): array
    {
        $query = ExamUser::query()
            ->where('status', ExamUserStatus::NORMAL->value)
            ->where('is_done', ExamUserIsDone::NO->value);
        $page = 1;
        $size = 1000;
        $total = $success = 0;
        while (true) {
            $logPrefix = "[UPDATE_EXAM_PROGRESS], page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            $count = $rows->count();
            $total += $count;
            Logger::writeWithContext((string) ("{$logPrefix}, ".LegacyDb::lastQuery(false, 'json').", count: {$count}"), (string) 'info', (bool) false);
            if ($rows->isEmpty()) {
                Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            foreach ($rows as $row) {
                $result = $this->updateProgress($row);
                Logger::writeWithContext((string) ("{$logPrefix}, examUser: ".$row->toJson().', result type: '.gettype($result)), (string) 'info', (bool) false);
                if ($result) {
                    $success += 1;
                }
            }
            $page++;
        }
        $result = compact('total', 'success');
        Logger::writeWithContext((string) ("{$logPrefix}, result: ".json_encode($result)), (string) 'info', (bool) false);

        return $result;
    }
}
