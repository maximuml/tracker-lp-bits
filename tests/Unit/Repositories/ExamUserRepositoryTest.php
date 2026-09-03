<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ExamUserStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ExamUserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ExamUserRepository.
 *
 * Covers listUser(), removeExamUser(), avoidExamUser(), removeExamUserBulk(),
 * avoidExamUserBulk(), and recoverExamUser().
 *
 * assignToUser() and updateExamUserEnd() are excluded — they depend on
 * Auth::user(), ExamRepository::isExamMatchUser(), ExamProgressRepository
 * progress calculation, and Message::add() event firing, which are better
 * suited to feature-level integration tests.
 */
final class ExamUserRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ExamUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('exam_progress')->delete();
        DB::table('exam_users')->delete();
        DB::table('exams')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = app(ExamUserRepository::class);
    }

    public function test_list_user_returns_paginated_results(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $this->createExamUser($user->id, $examId);
        $this->createExamUser($user->id, $examId);

        $paginator = $this->repository->listUser([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_list_user_filters_by_uid(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $examId = $this->createExam();
        $this->createExamUser($user1->id, $examId);
        $this->createExamUser($user2->id, $examId);

        $paginator = $this->repository->listUser(['uid' => $user1->id]);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame($user1->id, (int) $items[0]->uid);
    }

    public function test_list_user_filters_by_exam_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId1 = $this->createExam();
        $examId2 = $this->createExam();
        $this->createExamUser($user->id, $examId1);
        $this->createExamUser($user->id, $examId2);

        $paginator = $this->repository->listUser(['exam_id' => $examId1]);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame($examId1, (int) $items[0]->exam_id);
    }

    public function test_list_user_filters_by_status(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::NORMAL->value]);
        $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::AVOIDED->value]);

        $paginator = $this->repository->listUser(['status' => ExamUserStatus::NORMAL->value]);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame(ExamUserStatus::NORMAL->value, (int) $items[0]->status);
    }

    public function test_list_user_filters_by_is_done(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $this->createExamUser($user->id, $examId, ['is_done' => 0]);
        $this->createExamUser($user->id, $examId, ['is_done' => 1]);

        $paginator = $this->repository->listUser(['is_done' => 1]);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame(1, (int) $items[0]->is_done);
    }

    public function test_list_user_sorts_by_allowed_field(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $first = $this->createExamUser($user->id, $examId);
        $this->createExamUser($user->id, $examId);

        $paginator = $this->repository->listUser(['sort_field' => 'id', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($first, (int) $items[0]->id);
    }

    public function test_list_user_falls_back_to_id_when_sort_field_not_allowed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $first = $this->createExamUser($user->id, $examId);
        $this->createExamUser($user->id, $examId);

        $paginator = $this->repository->listUser(['sort_field' => 'evil', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($first, (int) $items[0]->id);
    }

    public function test_remove_exam_user_deletes_record_and_progress(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId);
        DB::table('exam_progress')->insert([
            'exam_user_id' => $examUserId,
            'exam_id' => $examId,
            'uid' => $user->id,
            'torrent_id' => $torrent->id,
            'index' => 1,
            'value' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->repository->removeExamUser($examUserId);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('exam_users')->where('id', $examUserId)->count());
        $this->assertSame(0, DB::table('exam_progress')->where('exam_user_id', $examUserId)->count());
    }

    public function test_remove_exam_user_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->removeExamUser(999999);
    }

    public function test_avoid_exam_user_sets_status_to_avoided(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::NORMAL->value]);

        $result = $this->repository->avoidExamUser($examUserId);

        $this->assertTrue($result);
        $row = DB::table('exam_users')->where('id', $examUserId)->first();
        $this->assertNotNull($row);
        $this->assertSame(ExamUserStatus::AVOIDED->value, (int) $row->status);
    }

    public function test_avoid_exam_user_throws_when_not_normal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::AVOIDED->value]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->avoidExamUser($examUserId);
    }

    public function test_recover_exam_user_sets_status_to_normal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::AVOIDED->value]);

        $result = $this->repository->recoverExamUser($examUserId);

        $this->assertTrue($result);
        $row = DB::table('exam_users')->where('id', $examUserId)->first();
        $this->assertNotNull($row);
        $this->assertSame(ExamUserStatus::NORMAL->value, (int) $row->status);
    }

    public function test_recover_exam_user_throws_when_not_avoided(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $examUserId = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::NORMAL->value]);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->recoverExamUser($examUserId);
    }

    public function test_remove_exam_user_bulk_deletes_by_uid(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $id1 = $this->createExamUser($user->id, $examId);
        $id2 = $this->createExamUser($user->id, $examId);

        $result = $this->repository->removeExamUserBulk(['uid' => $user->id], $user);

        $this->assertGreaterThanOrEqual(2, $result);
        $this->assertSame(0, DB::table('exam_users')->where('id', $id1)->count());
        $this->assertSame(0, DB::table('exam_users')->where('id', $id2)->count());
    }

    public function test_remove_exam_user_bulk_deletes_by_ids(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $id1 = $this->createExamUser($user->id, $examId);
        $id2 = $this->createExamUser($user->id, $examId);

        $result = $this->repository->removeExamUserBulk(['id' => [$id1, $id2]], $user);

        $this->assertSame(2, $result);
    }

    public function test_remove_exam_user_bulk_throws_when_no_filter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->removeExamUserBulk([], $user);
    }

    public function test_avoid_exam_user_bulk_sets_normal_to_avoided(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $examId = $this->createExam();
        $id1 = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::NORMAL->value]);
        $id2 = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::NORMAL->value]);
        $id3 = $this->createExamUser($user->id, $examId, ['status' => ExamUserStatus::AVOIDED->value]);

        $affected = $this->repository->avoidExamUserBulk(['uid' => $user->id], $user);

        $this->assertSame(2, $affected);
        $this->assertSame(ExamUserStatus::AVOIDED->value, (int) DB::table('exam_users')->where('id', $id1)->value('status'));
        $this->assertSame(ExamUserStatus::AVOIDED->value, (int) DB::table('exam_users')->where('id', $id2)->value('status'));
        $this->assertSame(ExamUserStatus::AVOIDED->value, (int) DB::table('exam_users')->where('id', $id3)->value('status'));
    }

    public function test_avoid_exam_user_bulk_throws_when_no_filter(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->avoidExamUserBulk([], $user);
    }

    private function createExam(): int
    {
        return (int) DB::table('exams')->insertGetId([
            'name' => 'Test Exam',
            'description' => 'test',
            'duration' => 30,
            'indexes' => json_encode([]),
            'status' => 1,
            'is_discovered' => 0,
            'priority' => 0,
            'type' => 1,
            'success_reward_bonus' => 0,
            'fail_deduct_bonus' => 0,
            'max_user_count' => 0,
            'background_color' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            'begin' => now()->toDateTimeString(),
            'end' => now()->addDays(30)->toDateTimeString(),
            'progress' => json_encode([]),
            'is_done' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
