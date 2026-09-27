<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Collection;

interface ExamRepositoryInterface
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params);

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function store(array $params): Exam;

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function update(array $params, int $id): Exam;

    public function getDetail(int $id): Exam;

    /**
     * @return bool
     */
    public function delete(int $id);

    /**
     * @return mixed
     */
    public function listIndexes();

    /**
     * @param  mixed  $excludeId
     * @param  mixed  $isDiscovered
     * @param  mixed  $type
     * @return \Illuminate\Database\Eloquent\Collection<int, Exam>
     */
    public function listValid($excludeId = null, $isDiscovered = null, $type = null);

    /**
     * @return Collection<int, Exam>
     */
    public function listMatchExam(int $uid);

    public function isExamMatchUser(Exam $exam, User|int $user): bool;
}
