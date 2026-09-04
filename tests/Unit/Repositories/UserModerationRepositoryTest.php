<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Repositories\UserModerationRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UserModerationRepository.
 *
 * Covers getModComment(), confirmUser(), removeWarnings().
 */
final class UserModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserModerationRepository(app(ToolRepository::class));
    }

    public function test_get_mod_comment_returns_latest_modify_log(): void
    {
        $user = User::factory()->create();
        $user->modifyLogs()->create(['content' => 'First comment']);
        $user->modifyLogs()->create(['content' => 'Second comment']);

        $result = $this->repository->getModComment($user->id);

        $this->assertSame('Second comment', $result);
    }

    public function test_get_mod_comment_returns_empty_string_when_no_logs(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->getModComment($user->id);

        $this->assertSame('', $result);
    }

    public function test_get_mod_comment_throws_for_nonexistent_user(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getModComment(999999);
    }

    public function test_confirm_user_confirms_pending_users(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PENDING->value]);

        $result = $this->repository->confirmUser($user->id);

        $this->assertTrue($result);
        $this->assertSame(UserStatus::CONFIRMED->value, User::query()->where('id', $user->id)->value('status'));
    }

    public function test_confirm_user_returns_true_when_no_pending_users(): void
    {
        $user = User::factory()->create(['status' => UserStatus::CONFIRMED->value]);

        $result = $this->repository->confirmUser($user->id);

        $this->assertTrue($result);
    }

    public function test_confirm_user_handles_array_of_ids(): void
    {
        $user1 = User::factory()->create(['status' => UserStatus::PENDING->value]);
        $user2 = User::factory()->create(['status' => UserStatus::PENDING->value]);

        $result = $this->repository->confirmUser([$user1->id, $user2->id]);

        $this->assertTrue($result);
        $this->assertSame(UserStatus::CONFIRMED->value, User::query()->where('id', $user1->id)->value('status'));
        $this->assertSame(UserStatus::CONFIRMED->value, User::query()->where('id', $user2->id)->value('status'));
    }

    public function test_remove_warnings_clears_warning_for_warned_users(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::SYSOP->value]);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'warned' => 1,
            'warneduntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->repository->removeWarnings($operator, [$user->id]);

        $updated = User::query()->find($user->id);
        $this->assertFalse((bool) $updated->warned);
        $this->assertNull($updated->warneduntil);
    }

    public function test_remove_warnings_skips_non_warned_users(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::SYSOP->value]);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['warned' => 0]);

        $this->repository->removeWarnings($operator, [$user->id]);

        $updated = User::query()->find($user->id);
        $this->assertFalse((bool) $updated->warned);
    }

    public function test_remove_warnings_handles_empty_array(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::SYSOP->value]);

        $this->repository->removeWarnings($operator, []);

        $this->expectNotToPerformAssertions();
    }

    public function test_remove_warnings_skips_nonexistent_users(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::SYSOP->value]);

        $this->repository->removeWarnings($operator, [999999]);

        $this->expectNotToPerformAssertions();
    }

    public function test_remove_warnings_logs_modcomment_via_modify_logs(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::SYSOP->value, 'username' => 'AdminOp']);
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'warned' => 1,
            'warneduntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->repository->removeWarnings($operator, [$user->id]);

        $latestLog = $user->modifyLogs()->orderByDesc('id')->first();
        $this->assertNotNull($latestLog);
        $this->assertStringContainsString('Warning Removed By AdminOp', $latestLog->content);
    }
}
