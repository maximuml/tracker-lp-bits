<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Repositories\UserModerationRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for UserModerationRepository.
 *
 * Covers getModComment(), confirmUser(), removeWarnings().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UserModerationRepository::class);
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
        $this->assertSame(UserStatus::CONFIRMED, User::query()->where('id', $user->id)->value('status'));
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
        $this->assertSame(UserStatus::CONFIRMED, User::query()->where('id', $user1->id)->value('status'));
        $this->assertSame(UserStatus::CONFIRMED, User::query()->where('id', $user2->id)->value('status'));
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

    public function test_remove_warnings_skips_equal_and_higher_class_targets(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        $peer = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        $admin = User::factory()->create(['class' => UserClassEnum::ADMINISTRATOR->value]);
        $lower = User::factory()->create(['class' => UserClassEnum::USER->value]);
        DB::table('users')->whereIn('id', [$peer->id, $admin->id, $lower->id])
            ->update(['warned' => 1, 'warneduntil' => now()->addDays(7)->toDateTimeString()]);

        $this->repository->removeWarnings($operator, [$peer->id, $admin->id, $lower->id]);

        $this->assertSame(1, (int) User::query()->where('id', $peer->id)->value('warned'));
        $this->assertSame(1, (int) User::query()->where('id', $admin->id)->value('warned'));
        $this->assertSame(0, (int) User::query()->where('id', $lower->id)->value('warned'));
    }

    public function test_remove_warnings_denies_operator_on_self(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        DB::table('users')->where('id', $operator->id)->update([
            'warned' => 1,
            'warneduntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->repository->removeWarnings($operator, [$operator->id]);

        $this->assertSame(1, (int) User::query()->where('id', $operator->id)->value('warned'));
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

    public function test_remove_leech_warn_denies_self_removal_for_regular_user(): void
    {
        $user = User::factory()->create(['class' => UserClassEnum::PEASANT->value]);
        DB::table('users')->where('id', $user->id)->update([
            'leechwarn' => 1,
            'leechwarnuntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        try {
            $this->repository->removeLeechWarn($user, $user->id);
            $this->fail('Expected InsufficientPermissionException');
        } catch (InsufficientPermissionException) {
            $this->assertSame(1, (int) User::query()->where('id', $user->id)->value('leechwarn'));
        }
    }

    public function test_remove_leech_warn_denies_regular_operator_on_other_user(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::PEASANT->value]);
        $user = User::factory()->create(['class' => UserClassEnum::PEASANT->value]);

        $this->expectException(InsufficientPermissionException::class);

        $this->repository->removeLeechWarn($operator, $user->id);
    }

    public function test_remove_leech_warn_allows_staff_on_lower_class_user(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        $user = User::factory()->create(['class' => UserClassEnum::PEASANT->value]);
        DB::table('users')->where('id', $user->id)->update([
            'leechwarn' => 1,
            'leechwarnuntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->assertTrue($this->repository->removeLeechWarn($operator, $user->id));

        $updated = User::query()->find($user->id);
        $this->assertSame(0, (int) $updated->leechwarn);
        $this->assertNull($updated->leechwarnuntil);
    }

    public function test_remove_leech_warn_allows_staff_on_self(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        DB::table('users')->where('id', $operator->id)->update([
            'leechwarn' => 1,
            'leechwarnuntil' => now()->addDays(7)->toDateTimeString(),
        ]);

        $this->assertTrue($this->repository->removeLeechWarn($operator, $operator->id));
        $this->assertSame(0, (int) User::query()->where('id', $operator->id)->value('leechwarn'));
    }

    public function test_remove_leech_warn_denies_equal_class_target(): void
    {
        $operator = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);
        $user = User::factory()->create(['class' => UserClassEnum::MODERATOR->value]);

        $this->expectException(InsufficientPermissionException::class);

        $this->repository->removeLeechWarn($operator, $user->id);
    }
}
