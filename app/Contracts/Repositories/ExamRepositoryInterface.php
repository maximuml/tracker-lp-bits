<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\User;
use Carbon\Carbon;

interface ExamRepositoryInterface
{
    public function getList(array $params);

    public function store(array $params): Exam;

    public function update(array $params, int $id): Exam;

    public function getDetail(int $id): Exam;

    public function delete(int $id);

    public function listIndexes();

    public function listValid($excludeId = null, $isDiscovered = null, $type = null);

    public function listMatchExam(int $uid);

    public function listMatchTask(int $uid);

    public function isExamMatchUser(Exam $exam, User|int $user): bool;

    public function assignToUser(int $uid, int $examId, $begin = null, $end = null);

    public function listUser(array $params);

    public function removeExamUser(int $examUserId);

    public function avoidExamUser(int $examUserId);

    public function updateExamUserEnd(ExamUser $examUser, Carbon $end, string $reason = '');

    public function removeExamUserBulk(array $params, User $user);

    public function avoidExamUserBulk(array $params, User $user): int;

    public function recoverExamUser(int $examUserId);

    public function updateProgress($examUser, ?User $user = null): ExamUser|bool;

    public function getUserExamProgress($uid, $status = null);

    public function getProgressFormatted(Exam $exam, array $progress, $locale = null);

    public function updateProgressBulk(): array;

    public function cronjonAssign();

    public function fetchUserAndDoAssign(Exam $exam): int|bool;

    public function cronjobCheckout($ignoreTimeRange = false): int;
}
