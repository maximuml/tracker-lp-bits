<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ExamDiscovered;
use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\ExamUserIsDone;
use App\Enums\ExamUserStatus;
use App\Enums\UserClass;
use App\Enums\UserDonate;
use App\Enums\UserEnabled;
use App\Enums\UserStatus;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamCronRepository;
use App\Repositories\ExamProgressRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ExamCronRepository.
 *
 * Covers cronjonAssign(), fetchUserAndDoAssign(), and cronjobCheckout().
 *
 * These are the cron-job methods that assign exams to eligible users and
 * check out expired exam users (marking them as finished, sending messages,
 * banning failing users, or rewarding passing users).
 */
final class ExamCronRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ExamCronRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('exam_progress')->delete();
        DB::table('exam_users')->delete();
        DB::table('exams')->delete();
        DB::table('messages')->delete();
        DB::table('bonus_logs')->delete();
        DB::table('user_ban_logs')->delete();
        DB::table('user_modify_logs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new ExamCronRepository(new ExamProgressRepository);
    }

    protected function tearDown(): void
    {
        Model::reguard();
        parent::tearDown();
    }

    public function test_cronjon_assign_returns_false_when_no_valid_exams(): void
    {
        $this->assertFalse($this->repository->cronjonAssign());
    }

    public function test_cronjon_assign_assigns_exam_to_eligible_users(): void
    {
        $exam = $this->createExam([
            'begin' => now()->subDay()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
        ]);
        $this->createEligibleUser();

        $result = $this->repository->cronjonAssign();

        $this->assertGreaterThan(0, $result);
        $this->assertGreaterThan(0, ExamUser::query()->where('exam_id', $exam->id)->count());
    }

    public function test_fetch_user_and_do_assign_returns_false_for_invalid_donate_filter(): void
    {
        $exam = $this->createExam([
            'filters' => [ExamFilterUser::DONATE->value => ['invalid_value']],
        ]);

        $result = $this->repository->fetchUserAndDoAssign($exam);

        $this->assertFalse($result);
    }

    public function test_fetch_user_and_do_assign_creates_exam_user_for_matching_user(): void
    {
        $exam = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        $result = $this->repository->fetchUserAndDoAssign($exam);

        $this->assertGreaterThan(0, $result);
        $examUser = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->first();
        $this->assertNotNull($examUser);
        $this->assertSame(ExamUserStatus::NORMAL->value, (int) $examUser->status);
    }

    public function test_fetch_user_and_do_assign_does_not_assign_to_user_with_existing_exam(): void
    {
        $exam = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        // Assign exam first time
        $this->repository->fetchUserAndDoAssign($exam);
        $countAfterFirst = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->count();

        // Try to assign again — should not duplicate
        $this->repository->fetchUserAndDoAssign($exam);
        $countAfterSecond = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->count();

        $this->assertSame(1, $countAfterFirst);
        $this->assertSame(1, $countAfterSecond);
    }

    public function test_fetch_user_and_do_assign_does_not_assign_to_user_with_other_normal_exam(): void
    {
        $exam1 = $this->createExam();
        $exam2 = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        // Assign exam1
        $this->repository->fetchUserAndDoAssign($exam1);

        // Try to assign exam2 — user already has a normal exam
        $this->repository->fetchUserAndDoAssign($exam2);

        $this->assertSame(0, ExamUser::query()->where('exam_id', $exam2->id)->where('uid', $user->id)->count());
    }

    public function test_fetch_user_and_do_assign_filters_by_user_class(): void
    {
        $exam = $this->createExam([
            'filters' => [ExamFilterUser::USER_CLASS->value => [UserClass::VIP->value]],
        ]);
        /** @var User $matchingUser */
        $matchingUser = $this->createEligibleUser(['class' => UserClass::VIP->value]);
        /** @var User $nonMatchingUser */
        $nonMatchingUser = $this->createEligibleUser(['class' => UserClass::USER->value]);

        $this->repository->fetchUserAndDoAssign($exam);

        $this->assertSame(1, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $matchingUser->id)->count());
        $this->assertSame(0, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $nonMatchingUser->id)->count());
    }

    public function test_fetch_user_and_do_assign_filters_by_donor_status(): void
    {
        $exam = $this->createExam([
            'filters' => [ExamFilterUser::DONATE->value => [UserDonate::YES->value]],
        ]);
        /** @var User $donor */
        $donor = $this->createEligibleUser(['donor' => true, 'donoruntil' => now()->addMonth()->toDateTimeString()]);
        /** @var User $nonDonor */
        $nonDonor = $this->createEligibleUser(['donor' => false, 'donoruntil' => null]);

        $this->repository->fetchUserAndDoAssign($exam);

        $this->assertSame(1, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $donor->id)->count());
        $this->assertSame(0, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $nonDonor->id)->count());
    }

    public function test_fetch_user_and_do_assign_skips_disabled_users(): void
    {
        $exam = $this->createExam();
        /** @var User $disabledUser */
        $disabledUser = $this->createEligibleUser(['enabled' => UserEnabled::NO->value]);

        $this->repository->fetchUserAndDoAssign($exam);

        $this->assertSame(0, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $disabledUser->id)->count());
    }

    public function test_fetch_user_and_do_assign_skips_pending_users(): void
    {
        $exam = $this->createExam();
        /** @var User $pendingUser */
        $pendingUser = $this->createEligibleUser(['status' => UserStatus::PENDING->value]);

        $this->repository->fetchUserAndDoAssign($exam);

        $this->assertSame(0, ExamUser::query()->where('exam_id', $exam->id)->where('uid', $pendingUser->id)->count());
    }

    public function test_cronjob_checkout_returns_zero_when_no_expired_exam_users(): void
    {
        $result = $this->repository->cronjobCheckout();

        $this->assertSame(0, $result);
    }

    public function test_cronjob_checkout_processes_expired_exam_user(): void
    {
        $exam = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        // Assign exam
        $this->repository->fetchUserAndDoAssign($exam);
        $examUser = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->first();
        $this->assertNotNull($examUser);

        // Expire the exam user
        $examUser->update(['end' => now()->subDay()->toDateTimeString()]);

        $result = $this->repository->cronjobCheckout();

        $this->assertGreaterThan(0, $result);
        $examUser->refresh();
        $this->assertSame(ExamUserStatus::FINISHED->value, (int) $examUser->status);
        // Progress should be deleted
        $this->assertSame(0, ExamProgress::query()->where('exam_user_id', $examUser->id)->count());
        // Message should be sent
        $this->assertGreaterThan(0, DB::table('messages')->where('receiver', $user->id)->count());
    }

    public function test_cronjob_checkout_bans_user_who_fails_exam(): void
    {
        // Create exam with impossible requirement so user fails
        $exam = $this->createExam([
            'indexes' => [[
                'index' => ExamIndex::UPLOADED->value,
                'checked' => true,
                'require_value' => 999999,
            ]],
        ]);
        /** @var User $user */
        $user = $this->createEligibleUser(['uploaded' => 0]);

        $this->repository->fetchUserAndDoAssign($exam);
        $examUser = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->first();
        $this->assertNotNull($examUser);
        $examUser->update(['end' => now()->subDay()->toDateTimeString()]);

        $this->repository->cronjobCheckout();

        $user->refresh();
        $this->assertFalse((bool) $user->enabled);
        $this->assertGreaterThan(0, DB::table('user_ban_logs')->where('uid', $user->id)->count());
    }

    public function test_cronjob_checkout_with_ignore_time_range_processes_all_normal_users(): void
    {
        $exam = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        $this->repository->fetchUserAndDoAssign($exam);
        $examUser = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->first();
        $this->assertNotNull($examUser);
        // Set end to future — not expired
        $examUser->update(['end' => now()->addDay()->toDateTimeString()]);

        // With ignoreTimeRange=true, should still process
        $result = $this->repository->cronjobCheckout(true);

        $this->assertGreaterThan(0, $result);
        $examUser->refresh();
        $this->assertSame(ExamUserStatus::FINISHED->value, (int) $examUser->status);
    }

    public function test_cronjob_checkout_does_not_process_normal_non_expired_users(): void
    {
        $exam = $this->createExam();
        /** @var User $user */
        $user = $this->createEligibleUser();

        $this->repository->fetchUserAndDoAssign($exam);
        $examUser = ExamUser::query()->where('exam_id', $exam->id)->where('uid', $user->id)->first();
        $this->assertNotNull($examUser);
        // Set end to future — not expired
        $examUser->update(['end' => now()->addDay()->toDateTimeString()]);

        $result = $this->repository->cronjobCheckout();

        $this->assertSame(0, $result);
        $examUser->refresh();
        $this->assertSame(ExamUserStatus::NORMAL->value, (int) $examUser->status);
    }

    public function test_cronjob_checkout_removes_orphaned_exam_user_without_user(): void
    {
        $exam = $this->createExam();

        // Create exam user with non-existent uid (disable FK checks for this insert)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $examUser = ExamUser::query()->create([
            'uid' => 999999,
            'exam_id' => $exam->id,
            'begin' => now()->subDay()->toDateTimeString(),
            'end' => now()->subHour()->toDateTimeString(),
            'status' => ExamUserStatus::NORMAL->value,
            'is_done' => ExamUserIsDone::NO->value,
            'progress' => [],
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository->cronjobCheckout();

        $this->assertSame(0, ExamUser::query()->where('id', $examUser->id)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExam(array $overrides = []): Exam
    {
        $data = array_merge([
            'name' => 'Test Exam',
            'description' => 'Test Description',
            'begin' => now()->subDay()->toDateTimeString(),
            'end' => now()->addDay()->toDateTimeString(),
            'duration' => 0,
            'filters' => [],
            'indexes' => [[
                'index' => ExamIndex::UPLOADED->value,
                'checked' => true,
                'require_value' => 0,
            ]],
            'status' => ExamStatus::ENABLED->value,
            'is_discovered' => ExamDiscovered::YES->value,
            'type' => ExamType::EXAM->value,
            'success_reward_bonus' => 0,
            'fail_deduct_bonus' => 0,
            'priority' => 0,
            'recurring' => null,
            'background_color' => '',
        ], $overrides);

        return Exam::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEligibleUser(array $overrides = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'enabled' => UserEnabled::YES->value,
            'status' => UserStatus::CONFIRMED->value,
            'class' => UserClass::USER->value,
            'uploaded' => 1024 * 1024 * 1024,
        ], $overrides));

        return $user;
    }
}
