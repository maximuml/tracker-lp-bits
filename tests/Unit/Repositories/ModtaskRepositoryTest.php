<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\ModtaskRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ModtaskRepository.
 *
 * Covers confirmUser(), getUserArray(), addFund(), updateUser(), and
 * addWarning() public methods.
 */
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
            'status' => 'pending',
            'info' => 'awaiting review',
        ]);

        $this->repository->confirmUser($user->id, 'confirmed');

        $record = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame('confirmed', $record->status);
        $this->assertNull($record->info);
    }

    public function test_get_user_array_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUserArray(999999));
    }

    public function test_get_user_array_returns_array_with_passhash_visible(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserArray($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, (int) $result['id']);
        // passhash is in $hidden on the User model but makeVisible() exposes it.
        $this->assertArrayHasKey('passhash', $result);
        $this->assertSame($user->passhash, $result['passhash']);
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

    public function test_add_warning_increments_timeswarned(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['timeswarned' => 0]);

        $this->repository->addWarning($user->id, []);

        $record = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->timeswarned);
    }

    public function test_add_warning_increments_timeswarned_with_extra_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['timeswarned' => 2]);

        $this->repository->addWarning($user->id, [
            'warnedby' => 42,
            'warneduntil' => '2025-12-31 23:59:59',
        ]);

        $record = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(3, (int) $record->timeswarned);
        $this->assertSame(42, (int) $record->warnedby);
        $this->assertSame('2025-12-31 23:59:59', $record->warneduntil);
    }
}
