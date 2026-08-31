<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ExamUserStatus;
use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\User;
use App\Support\Json;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Handles exam-user assignment, lifecycle, and bulk operations.
 *
 * Extracted from ExamRepository to reduce god-object surface area.
 */
class ExamUserRepository extends BaseRepository
{
    public function __construct(
        private readonly ExamProgressRepository $examProgressRepository,
    ) {}

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

        $examRepo = app(ExamRepository::class);
        if (! $examRepo->isExamMatchUser($exam, $user)) {
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
        $this->examProgressRepository->updateProgress($examUser, $user);

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
            'sender' => null,
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
}
