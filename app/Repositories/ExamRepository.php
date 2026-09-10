<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Enums\ExamFilterUser;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
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
class ExamRepository extends BaseRepository implements ExamRepositoryInterface
{
    public function __construct(
        private readonly ExamUserRepository $examUserRepository,
        private readonly ExamProgressRepository $examProgressRepository,
        private readonly ExamCronRepository $examCronRepository,
        private readonly ExamValidator $examValidator = new ExamValidator,
    ) {}

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
        $diffInHours = $this->examValidator->checkBeginEnd($params);
        $this->examValidator->checkIndexes($params, $diffInHours);
        $this->examValidator->checkFilters($params);
        $formatted = $this->examValidator->formatParams($params);
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
        $diffInHours = $this->examValidator->checkBeginEnd($params);
        $this->examValidator->checkIndexes($params, $diffInHours);
        $this->examValidator->checkFilters($params);
        $exam = Exam::query()->findOrFail($id);
        $formatted = $this->examValidator->formatParams($params);
        /** @var array<string, mixed> $data */
        $data = $formatted;
        $exam->update($data);

        return $exam;
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

        if ($excludeId !== null) {
            $excludeIds = is_array($excludeId) ? $excludeId : [$excludeId];
            $query->whereNotIn('id', $excludeIds);
        }
        if ($isDiscovered !== null) {
            $query->where('is_discovered', $isDiscovered);
        }
        if ($type !== null) {
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
        return $this->examUserRepository->assignToUser($uid, $examId, $begin, $end);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function listUser(array $params)
    {
        return $this->examUserRepository->listUser($params);
    }

    /**
     * @return mixed
     */
    public function removeExamUser(int $examUserId)
    {
        return $this->examUserRepository->removeExamUser($examUserId);
    }

    /**
     * @return mixed
     */
    public function avoidExamUser(int $examUserId)
    {
        return $this->examUserRepository->avoidExamUser($examUserId);
    }

    public function updateExamUserEnd(ExamUser $examUser, Carbon $end, string $reason = ''): void
    {
        $this->examUserRepository->updateExamUserEnd($examUser, $end, $reason);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function removeExamUserBulk(array $params, User $user)
    {
        return $this->examUserRepository->removeExamUserBulk($params, $user);
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function avoidExamUserBulk(array $params, User $user): int
    {
        return $this->examUserRepository->avoidExamUserBulk($params, $user);
    }

    /**
     * @return mixed
     */
    public function recoverExamUser(int $examUserId)
    {
        return $this->examUserRepository->recoverExamUser($examUserId);
    }

    /**
     * @param  mixed  $examUser
     */
    public function updateProgress($examUser, ?User $user = null): ExamUser|bool
    {
        return $this->examProgressRepository->updateProgress($examUser, $user);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $status
     * @return mixed|null
     */
    public function getUserExamProgress($uid, $status = null)
    {
        return $this->examProgressRepository->getUserExamProgress($uid, $status);
    }

    /**
     * @param  array<int|string, mixed>  $progress
     * @param  mixed  $locale
     * @return mixed
     */
    public function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        return $this->examProgressRepository->getProgressFormatted($exam, $progress, $locale);
    }

    /** @return  array<int|string, mixed> */
    public function updateProgressBulk(): array
    {
        return $this->examProgressRepository->updateProgressBulk();
    }

    /** @return  mixed */
    public function cronjonAssign()
    {
        return $this->examCronRepository->cronjonAssign();
    }

    public function fetchUserAndDoAssign(Exam $exam): bool|int
    {
        return $this->examCronRepository->fetchUserAndDoAssign($exam);
    }

    /** @param  mixed  $ignoreTimeRange */
    public function cronjobCheckout($ignoreTimeRange = false): int
    {
        return $this->examCronRepository->cronjobCheckout($ignoreTimeRange);
    }
}
