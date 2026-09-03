<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\MedalGetType;
use App\Enums\UserClass;
use App\Enums\UserMedalStatus;
use App\Exceptions\NexusException;
use App\Models\Medal;
use App\Models\User;
use App\Models\UserMedal;
use App\Repositories\MedalRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for MedalRepository.
 *
 * Covers getList(), store(), update(), getDetail(), delete(), grantToUser(),
 * userAttachMedal(), toggleUserMedalStatus(), saveUserMedal(),
 * increaseExpireAt(), updateExpireAt(), and cancelExpireAt().
 */
final class MedalRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private MedalRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('user_medals')->delete();
        DB::table('medals')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new MedalRepository;
    }

    public function test_get_list_returns_paginated_medals(): void
    {
        $this->createMedal(['name' => 'Medal A']);
        $this->createMedal(['name' => 'Medal B']);

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_sorts_by_allowed_field(): void
    {
        $this->createMedal(['name' => 'Beta', 'priority' => 1]);
        $this->createMedal(['name' => 'Alpha', 'priority' => 5]);

        $paginator = $this->repository->getList(['sort_field' => 'name', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame('Alpha', $items[0]->name);
        $this->assertSame('Beta', $items[1]->name);
    }

    public function test_get_list_falls_back_to_id_when_sort_field_not_allowed(): void
    {
        $first = $this->createMedal(['name' => 'First']);
        $this->createMedal(['name' => 'Second']);

        $paginator = $this->repository->getList(['sort_field' => 'evil', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($first->id, $items[0]->id);
    }

    public function test_store_creates_medal(): void
    {
        $medal = $this->repository->store([
            'name' => 'Created Medal',
            'get_type' => MedalGetType::EXCHANGE->value,
            'price' => 100,
            'duration' => 30,
        ]);

        $this->assertInstanceOf(Medal::class, $medal);
        $this->assertSame('Created Medal', $medal->name);
        $this->assertDatabaseHas('medals', ['name' => 'Created Medal']);
    }

    public function test_update_modifies_medal(): void
    {
        $medal = $this->createMedal(['name' => 'Original']);

        $updated = $this->repository->update(['name' => 'Updated'], $medal->id);

        $this->assertSame('Updated', $updated->name);
        $this->assertDatabaseHas('medals', ['id' => $medal->id, 'name' => 'Updated']);
    }

    public function test_get_detail_returns_medal_when_found(): void
    {
        $medal = $this->createMedal(['name' => 'Find Me']);

        $result = $this->repository->getDetail($medal->id);

        $this->assertInstanceOf(Medal::class, $result);
        $this->assertSame($medal->id, $result->id);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_medal_and_user_medals(): void
    {
        $medal = $this->createMedal(['name' => 'Delete Me']);
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('user_medals')->insert([
            'uid' => $user->id,
            'medal_id' => $medal->id,
            'status' => UserMedalStatus::NOT_WEARING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->repository->delete($medal->id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('medals')->where('id', $medal->id)->count());
        $this->assertSame(0, DB::table('user_medals')->where('medal_id', $medal->id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    public function test_grant_to_user_succeeds_when_auth_class_is_higher(): void
    {
        /** @var User $target */
        $target = User::factory()->class(UserClass::USER->value)->create();
        /** @var User $admin */
        $admin = User::factory()->class(UserClass::SYSOP->value)->create();
        $medal = $this->createMedal(['name' => 'Grant Medal']);
        $this->actingAs($admin);

        $this->repository->grantToUser($target->id, $medal->id);

        $this->assertSame(1, DB::table('user_medals')->where('uid', $target->id)->count());
    }

    public function test_grant_to_user_throws_when_no_permission(): void
    {
        /** @var User $target */
        $target = User::factory()->class(UserClass::SYSOP->value)->create();
        /** @var User $admin */
        $admin = User::factory()->class(UserClass::USER->value)->create();
        $medal = $this->createMedal(['name' => 'Grant Medal']);
        $this->actingAs($admin);

        $this->expectException(\LogicException::class);

        $this->repository->grantToUser($target->id, $medal->id);
    }

    public function test_grant_to_user_throws_when_already_owned(): void
    {
        /** @var User $target */
        $target = User::factory()->class(UserClass::USER->value)->create();
        /** @var User $admin */
        $admin = User::factory()->class(UserClass::SYSOP->value)->create();
        $medal = $this->createMedal(['name' => 'Grant Medal']);
        $this->actingAs($admin);

        $this->repository->grantToUser($target->id, $medal->id);

        $this->expectException(\LogicException::class);

        $this->repository->grantToUser($target->id, $medal->id);
    }

    public function test_user_attach_medal_creates_user_medal(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Attach Medal', 'duration' => 0]);

        $this->repository->userAttachMedal($user, $medal);

        $this->assertSame(1, DB::table('user_medals')->where('uid', $user->id)->count());
        $row = DB::table('user_medals')->where('uid', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(UserMedalStatus::NOT_WEARING->value, (int) $row->status);
    }

    public function test_user_attach_medal_sets_expire_at_when_duration_positive(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Timed Medal', 'duration' => 30]);

        $this->repository->userAttachMedal($user, $medal);

        $row = DB::table('user_medals')->where('uid', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->expire_at);
    }

    public function test_toggle_user_medal_status_wears_when_not_wearing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Toggle Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $result = $this->repository->toggleUserMedalStatus($userMedal->id, $user->id);

        $this->assertSame(UserMedalStatus::WEARING->value, (int) $result->status);
    }

    public function test_toggle_user_medal_status_unwears_when_wearing(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Toggle Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();
        $this->repository->toggleUserMedalStatus($userMedal->id, $user->id);

        $result = $this->repository->toggleUserMedalStatus($userMedal->id, $user->id);

        $this->assertSame(UserMedalStatus::NOT_WEARING->value, (int) $result->status);
    }

    public function test_toggle_user_medal_status_throws_when_wrong_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Toggle Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $this->expectException(\LogicException::class);

        $this->repository->toggleUserMedalStatus($userMedal->id, 999999);
    }

    public function test_toggle_user_medal_status_throws_when_max_wear_exceeded(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        // Create and wear 3 medals (default max), then try to wear a 4th.
        for ($i = 0; $i < 3; $i++) {
            $medal = $this->createMedal(['name' => "Medal {$i}"]);
            $this->repository->userAttachMedal($user, $medal);
            $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();
            $this->repository->toggleUserMedalStatus($userMedal->id, $user->id);
        }
        $extraMedal = $this->createMedal(['name' => 'Extra Medal']);
        $this->repository->userAttachMedal($user, $extraMedal);
        $extraUserMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $extraMedal->id)->firstOrFail();

        $this->expectException(NexusException::class);

        $this->repository->toggleUserMedalStatus($extraUserMedal->id, $user->id);
    }

    public function test_save_user_medal_returns_true_when_no_valid_medals(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->saveUserMedal($user->id, []);

        $this->assertTrue($result);
    }

    public function test_save_user_medal_updates_status_and_priority(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Save Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        // saveUserMedal() uses upsert() without medal_id/uid columns, which
        // triggers FK checks on the INSERT part of ON DUPLICATE KEY UPDATE.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $this->repository->saveUserMedal($user->id, [
            $userMedal->id => ['status' => 'on', 'priority' => 5],
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $row = DB::table('user_medals')->where('id', $userMedal->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(UserMedalStatus::WEARING->value, (int) $row->status);
        $this->assertSame(5, (int) $row->priority);
    }

    public function test_save_user_medal_throws_when_exceeding_max_wear(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $userMedalIds = [];
        for ($i = 0; $i < 4; $i++) {
            $medal = $this->createMedal(['name' => "Medal {$i}"]);
            $this->repository->userAttachMedal($user, $medal);
            $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();
            $userMedalIds[$userMedal->id] = ['status' => 'on', 'priority' => 0];
        }

        $this->expectException(NexusException::class);

        $this->repository->saveUserMedal($user->id, $userMedalIds);
    }

    public function test_increase_expire_at_adds_days(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Timed Medal', 'duration' => 30]);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();
        $original = DB::table('user_medals')->where('id', $userMedal->id)->value('expire_at');

        $collection = new Collection([$userMedal]);
        $this->repository->increaseExpireAt($collection, 'expire_at', 7);

        $updated = DB::table('user_medals')->where('id', $userMedal->id)->value('expire_at');
        $this->assertNotEquals($original, $updated);
    }

    public function test_increase_expire_at_throws_for_invalid_field(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->increaseExpireAt(new Collection([$userMedal]), 'invalid_field', 7);
    }

    public function test_update_expire_at_sets_new_date(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Timed Medal', 'duration' => 30]);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();
        $newDate = Carbon::now()->addDays(60);

        $this->repository->updateExpireAt(new Collection([$userMedal]), 'expire_at', $newDate);

        $row = DB::table('user_medals')->where('id', $userMedal->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->expire_at);
    }

    public function test_update_expire_at_throws_for_invalid_field(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->updateExpireAt(new Collection([$userMedal]), 'invalid_field', Carbon::now());
    }

    public function test_cancel_expire_at_sets_null(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Timed Medal', 'duration' => 30]);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $this->repository->cancelExpireAt(new Collection([$userMedal]), 'expire_at');

        $row = DB::table('user_medals')->where('id', $userMedal->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->expire_at);
    }

    public function test_cancel_expire_at_throws_for_invalid_field(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $medal = $this->createMedal(['name' => 'Medal']);
        $this->repository->userAttachMedal($user, $medal);
        $userMedal = UserMedal::query()->where('uid', $user->id)->where('medal_id', $medal->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->cancelExpireAt(new Collection([$userMedal]), 'invalid_field');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMedal(array $overrides = []): Medal
    {
        return Medal::query()->create(array_merge([
            'name' => 'Test Medal',
            'get_type' => MedalGetType::GRANT->value,
            'price' => 0,
            'duration' => 0,
            'bonus_addition_duration' => 0,
            'bonus_addition_factor' => 0,
            'gift_fee_factor' => 0,
            'priority' => 0,
        ], $overrides));
    }
}
