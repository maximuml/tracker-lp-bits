<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ExamIndex;
use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ExamProgressRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ExamProgressRepository.
 *
 * Covers addProgress(), updateProgress(), getUserExamProgress(),
 * calculateProgress(), getProgressFormatted(), and updateProgressBulk().
 */
final class ExamProgressRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ExamProgressRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('exam_progress')->delete();
        DB::table('exam_users')->delete();
        DB::table('exams')->delete();
        $this->repository = new ExamProgressRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Model::reguard();
        parent::tearDown();
    }

    public function test_add_progress_returns_false_when_no_exam_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->addProgress($user->id, 0, [ExamIndex::UPLOADED->value => 100]);

        $this->assertFalse($result);
    }

    public function test_add_progress_returns_false_when_outside_time_range(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, [
            'begin' => now()->addDay()->toDateTimeString(),
            'end' => now()->addDays(30)->toDateTimeString(),
        ]);

        $result = $this->repository->addProgress($user->id, 0, [ExamIndex::SEED_BONUS->value => 100]);

        $this->assertFalse($result);
    }

    public function test_add_progress_inserts_progress_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam([
            'indexes' => json_encode([
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100],
            ]),
        ]);
        $this->createExamUser($user->id, $examId);

        $this->repository->addProgress($user->id, 0, [ExamIndex::SEED_BONUS->value => 50]);

        $count = ExamProgress::query()->where('uid', $user->id)->where('index', ExamIndex::SEED_BONUS->value)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_add_progress_skips_unchecked_indexes(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => false, 'require_value' => 100],
            ]),
        ]);
        $this->createExamUser($user->id, $examId);

        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($user)->create();

        $this->repository->addProgress($user->id, $torrent->id, [ExamIndex::UPLOADED->value => 50]);

        $count = ExamProgress::query()->where('uid', $user->id)->count();
        $this->assertSame(0, $count);
    }

    public function test_update_progress_returns_false_when_no_exam_user(): void
    {
        $result = $this->repository->updateProgress(99999);

        $this->assertFalse($result);
    }

    public function test_update_progress_returns_false_when_status_not_normal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::AVOIDED->value]);

        $result = $this->repository->updateProgress($examUserId);

        $this->assertFalse($result);
    }

    public function test_update_progress_calculates_and_updates(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'seedbonus' => 200.0]);
        $examId = $this->createExam([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1],
            ]),
        ]);
        $examUserId = $this->createExamUser($user->id, $examId);
        $examUser = ExamUser::query()->find($examUserId);
        $this->assertNotNull($examUser);

        $result = $this->repository->updateProgress($examUser);

        $this->assertInstanceOf(ExamUser::class, $result);
        $this->assertNotNull($result->progress_formatted);
    }

    public function test_get_user_exam_progress_returns_null_when_no_exam_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserExamProgress($user->id);

        $this->assertNull($result);
    }

    public function test_get_user_exam_progress_returns_exam_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'seedbonus' => 200.0]);
        $examId = $this->createExam([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1],
            ]),
        ]);
        $this->createExamUser($user->id, $examId);

        $result = $this->repository->getUserExamProgress($user->id);

        $this->assertInstanceOf(ExamUser::class, $result);
        $this->assertNotNull($result->progress_formatted);
    }

    public function test_calculate_progress_returns_sum_by_index(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId);
        DB::table('exam_progress')->insert([
            [
                'exam_user_id' => $examUserId,
                'exam_id' => $examId,
                'uid' => $user->id,
                'torrent_id' => 1,
                'index' => ExamIndex::UPLOADED->value,
                'value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_user_id' => $examUserId,
                'exam_id' => $examId,
                'uid' => $user->id,
                'torrent_id' => 2,
                'index' => ExamIndex::UPLOADED->value,
                'value' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $examUser = ExamUser::query()->find($examUserId);
        $this->assertNotNull($examUser);

        $result = $this->repository->calculateProgress($examUser);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(ExamIndex::UPLOADED->value, $result);
        $this->assertSame(300, (int) $result[ExamIndex::UPLOADED->value]);
    }

    public function test_get_progress_formatted_returns_empty_for_unchecked(): void
    {
        $exam = $this->createExamModel([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => false, 'require_value' => 100],
            ]),
        ]);

        $result = $this->repository->getProgressFormatted($exam, []);

        $this->assertSame([], $result);
    }

    public function test_get_progress_formatted_marks_passed(): void
    {
        $exam = $this->createExamModel([
            'indexes' => json_encode([
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 100],
            ]),
        ]);

        $result = $this->repository->getProgressFormatted($exam, [ExamIndex::SEED_BONUS->value => 200]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
    }

    public function test_get_progress_formatted_marks_not_passed(): void
    {
        $exam = $this->createExamModel([
            'indexes' => json_encode([
                ['index' => ExamIndex::SEED_BONUS->value, 'checked' => true, 'require_value' => 500],
            ]),
        ]);

        $result = $this->repository->getProgressFormatted($exam, [ExamIndex::SEED_BONUS->value => 100]);

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['passed']);
    }

    public function test_get_progress_formatted_formats_uploaded_size(): void
    {
        $exam = $this->createExamModel([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1],
            ]),
        ]);

        $result = $this->repository->getProgressFormatted($exam, [ExamIndex::UPLOADED->value => 1073741824]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['passed']);
        $this->assertStringContainsString('GB', $result[0]['require_value_formatted']);
    }

    public function test_update_progress_bulk_returns_zero_when_no_exam_users(): void
    {
        $result = $this->repository->updateProgressBulk();

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['success']);
    }

    public function test_update_progress_bulk_processes_normal_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'seedbonus' => 200.0]);
        $examId = $this->createExam([
            'indexes' => json_encode([
                ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => 1],
            ]),
        ]);
        $this->createExamUser($user->id, $examId);

        $result = $this->repository->updateProgressBulk();

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['success']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExam(array $overrides = []): int
    {
        return (int) DB::table('exams')->insertGetId(array_merge([
            'name' => 'Test Exam',
            'description' => 'test',
            'duration' => 30,
            'indexes' => json_encode([]),
            'filters' => json_encode([]),
            'status' => 1,
            'is_discovered' => 0,
            'priority' => 0,
            'type' => 1,
            'success_reward_bonus' => 0,
            'fail_deduct_bonus' => 0,
            'max_user_count' => 0,
            'background_color' => '',
            'begin' => now()->subDay()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExamModel(array $overrides = []): Exam
    {
        $id = $this->createExam($overrides);

        return Exam::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExamUser(int $userId, int $examId, array $overrides = []): int
    {
        return (int) DB::table('exam_users')->insertGetId(array_merge([
            'uid' => $userId,
            'exam_id' => $examId,
            'status' => ExamUserStatus::NORMAL->value,
            'begin' => now()->subDay()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
            'progress' => json_encode([]),
            'is_done' => ExamUserIsDone::NO->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
