<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Exam;
use App\Models\User;

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
}
