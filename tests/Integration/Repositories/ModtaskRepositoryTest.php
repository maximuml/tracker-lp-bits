<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\ModtaskRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ModtaskRepository.
 *
 * Covers confirmUser(), getUserArray(), addFund(), and updateUser()
 * public methods.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ModtaskRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ModtaskRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ModtaskRepository;
    }

    public function test_confirm_user_updates_status_and_clears_info(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'status' => 0,
            'info' => 'awaiting review',
        ]);

        $this->repository->confirmUser($user->id, 'confirmed');

        $record = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(UserStatus::CONFIRMED->value, (int) $record->status);
        $this->assertNull($record->info);
    }

    public function test_get_user_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUser(999999));
    }

    public function test_get_user_returns_model_with_passhash_readable(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUser($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        // passhash is $hidden for serialization but attribute reads still work.
        $this->assertSame($user->passhash, $result->passhash);
    }

    public function test_add_fund_inserts_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->addFund($user->id, 10.50, 75.25, 'donation');

        $this->assertSame(1, DB::table('funds')->where('user', $user->id)->count());

        $record = DB::table('funds')->where('user', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame('10.50', (string) $record->usd);
        $this->assertSame('75.25', (string) $record->cny);
        $this->assertSame('donation', $record->memo);
        $this->assertNotNull($record->added);
    }

    public function test_update_user_updates_columns_and_returns_count(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $count = $this->repository->updateUser($user->id, ['enabled' => 0]);

        $this->assertSame(1, $count);

        $record = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(0, (int) $record->enabled);
    }

    public function test_update_user_returns_zero_when_not_found(): void
    {
        $count = $this->repository->updateUser(999999, ['enabled' => 0]);

        $this->assertSame(0, $count);
    }
}
