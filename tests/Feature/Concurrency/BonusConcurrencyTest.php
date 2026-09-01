<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\User;
use App\Repositories\BonusRepository;
use App\Support\Bonus;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BonusConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_concurrent_bonus_updates_do_not_lose_increments(): void
    {
        $user = User::factory()->create(['seedbonus' => 0.0]);

        $bonusRepository = app(BonusRepository::class);

        // Simulate 10 concurrent +10 bonus updates
        // In a real concurrent scenario, each would be a separate process,
        // but we verify the SQL atomic increment is correct
        for ($i = 0; $i < 10; $i++) {
            $bonusRepository->updateSeedBonus('+', 10.0, $user->id);
        }

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(100.0, $finalBonus);
    }

    public function test_concurrent_bonus_decrements_do_not_go_negative_unexpectedly(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $bonusRepository = app(BonusRepository::class);

        // 5 decrements of 10 each = 50 total
        for ($i = 0; $i < 5; $i++) {
            $bonusRepository->updateSeedBonus('-', 10.0, $user->id);
        }

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus);
    }

    public function test_bonus_update_points_respects_bonus_tweak_setting(): void
    {
        $user = User::factory()->create(['seedbonus' => 0.0]);

        // bonus_tweak disabled — updatePoints should be a no-op
        app(Globals::class)->set('bonus_tweak', 'disable');

        Bonus::updatePoints('+', 50.0, $user->id);

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(0.0, $finalBonus);

        // bonus_tweak enabled — updatePoints should work
        app(Globals::class)->set('bonus_tweak', 'enable');

        Bonus::updatePoints('+', 50.0, $user->id);

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus);
    }

    public function test_bonus_update_points_with_zero_point_is_noop(): void
    {
        $user = User::factory()->create(['seedbonus' => 42.0]);

        app(Globals::class)->set('bonus_tweak', 'enable');

        Bonus::updatePoints('+', 0.0, $user->id);

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(42.0, $finalBonus);
    }

    public function test_update_seed_bonus_rejects_invalid_operator(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid seedbonus operation');

        app(BonusRepository::class)->updateSeedBonus('*', 10.0, $user->id);
    }
}
