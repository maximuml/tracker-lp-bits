<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamCronRepository;
use App\Repositories\ExamProgressRepository;
use App\Repositories\ExamRepository;
use App\Repositories\ExamUserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for ExamRepository.
 *
 * Covers getList(), getDetail(), delete(), listIndexes(),
 * listValid(), and isExamMatchUser().
 */
final class ExamRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ExamRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ExamRepository(
            app(ExamUserRepository::class),
            app(ExamProgressRepository::class),
            app(ExamCronRepository::class),
        );
    }

    public function test_get_list_returns_paginated_exams(): void
    {
        Exam::factory()->create(['name' => 'Test exam 1']);
        Exam::factory()->create(['name' => 'Test exam 2']);

        $result = $this->repository->getList([]);

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(2, $result->total());
    }

    public function test_get_detail_returns_exam_by_id(): void
    {
        $exam = Exam::factory()->create(['name' => 'Detail test']);

        $found = $this->repository->getDetail($exam->id);

        $this->assertSame($exam->id, $found->id);
        $this->assertSame('Detail test', $found->name);
    }

    public function test_get_detail_throws_for_nonexistent_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_exam_and_related_records(): void
    {
        $user = User::factory()->create();
        $exam = Exam::factory()->create();
        ExamUser::query()->create([
            'exam_id' => $exam->id,
            'uid' => $user->id,
            'begin' => now()->toDateTimeString(),
            'end' => now()->addDays(30)->toDateTimeString(),
            'status' => 0,
        ]);

        $result = $this->repository->delete($exam->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('exam_users', ['exam_id' => $exam->id]);
    }

    public function test_delete_throws_for_nonexistent_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    public function test_list_indexes_returns_array(): void
    {
        $indexes = $this->repository->listIndexes();

        $this->assertIsArray($indexes);
        $this->assertNotEmpty($indexes);
    }

    public function test_list_valid_returns_only_enabled_exams(): void
    {
        Exam::factory()->create([
            'status' => ExamStatus::ENABLED->value,
            'duration' => 30,
            'begin' => null,
            'end' => null,
        ]);
        Exam::factory()->create([
            'status' => ExamStatus::DISABLED->value,
            'duration' => 30,
            'begin' => null,
            'end' => null,
        ]);

        $valid = $this->repository->listValid();

        $this->assertGreaterThan(0, $valid->count());
        foreach ($valid as $exam) {
            $this->assertSame(ExamStatus::ENABLED->value, $exam->status);
        }
    }

    public function test_list_valid_filters_by_type(): void
    {
        Exam::factory()->create([
            'status' => ExamStatus::ENABLED->value,
            'type' => ExamType::EXAM->value,
            'duration' => 30,
        ]);
        Exam::factory()->create([
            'status' => ExamStatus::ENABLED->value,
            'type' => ExamType::TASK->value,
            'duration' => 30,
        ]);

        $exams = $this->repository->listValid(null, null, ExamType::EXAM->value);

        foreach ($exams as $exam) {
            $this->assertSame(ExamType::EXAM->value, $exam->type);
        }
    }

    public function test_list_valid_excludes_by_id(): void
    {
        $exam1 = Exam::factory()->create(['status' => ExamStatus::ENABLED->value, 'duration' => 30]);
        $exam2 = Exam::factory()->create(['status' => ExamStatus::ENABLED->value, 'duration' => 30]);

        $valid = $this->repository->listValid($exam1->id);

        foreach ($valid as $exam) {
            $this->assertNotSame($exam1->id, $exam->id);
        }
    }

    public function test_list_valid_filters_by_is_discovered(): void
    {
        Exam::factory()->create(['status' => ExamStatus::ENABLED->value, 'is_discovered' => 1, 'duration' => 30]);
        Exam::factory()->create(['status' => ExamStatus::ENABLED->value, 'is_discovered' => 0, 'duration' => 30]);

        $discovered = $this->repository->listValid(null, 1);

        foreach ($discovered as $exam) {
            $this->assertSame(1, $exam->is_discovered);
        }
    }
}
