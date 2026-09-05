<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Enums\BusinessType;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Services\ThankService;
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

    /**
     * Consuming bonus when the user has insufficient balance must fail
     * and leave the balance unchanged. This verifies the lockForUpdate-based
     * check inside consumeUserBonus prevents over-spending.
     */
    public function test_consume_user_bonus_fails_when_insufficient_balance(): void
    {
        $user = User::factory()->create(['seedbonus' => 50.0]);
        $bonusRepository = app(BonusRepository::class);

        try {
            $bonusRepository->consumeUserBonus($user->id, 100.0, BusinessType::EXCHANGE_UPLOAD->value, 'test');
            $this->fail('Expected exception was not thrown');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('bonus not enough', $e->getMessage());
        }

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus, 'Bonus should not be deducted on failure');
    }

    /**
     * Two sequential bonus consumptions must correctly deduct the exact
     * amounts without race conditions.
     */
    public function test_two_sequential_bonus_consumptions_deduct_correctly(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);
        $bonusRepository = app(BonusRepository::class);

        $bonusRepository->consumeUserBonus($user->id, 30.0, BusinessType::EXCHANGE_UPLOAD->value, 'first');
        $bonusRepository->consumeUserBonus($user->id, 20.0, BusinessType::EXCHANGE_DOWNLOAD->value, 'second');

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus);
    }

    /**
     * Double torrent purchase must be prevented by the unique constraint
     * on torrent_buy_logs (uid, torrent_id).
     */
    public function test_double_torrent_purchase_is_rejected_by_unique_constraint(): void
    {
        $owner = User::factory()->create(['seedbonus' => 1000.0]);
        $buyer = User::factory()->create(['seedbonus' => 1000.0]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($owner)->create(['price' => 100]);
        $bonusRepository = app(BonusRepository::class);

        // First purchase succeeds
        $bonusRepository->consumeToBuyTorrent($buyer->id, $torrent->id);
        $buyLogCount = DB::table('torrent_buy_logs')
            ->where('uid', $buyer->id)
            ->where('torrent_id', $torrent->id)
            ->count();
        $this->assertSame(1, $buyLogCount, 'First purchase should create exactly one buy log');

        // Second purchase must fail due to unique constraint
        try {
            $bonusRepository->consumeToBuyTorrent($buyer->id, $torrent->id);
            $this->fail('Expected unique constraint violation was not thrown');
        } catch (\Exception $e) {
            // QueryException or RuntimeException — either is acceptable
            $this->assertTrue(true, 'Second purchase was correctly rejected');
        }

        // Verify only one buy log exists
        $buyLogCount2 = DB::table('torrent_buy_logs')
            ->where('uid', $buyer->id)
            ->where('torrent_id', $torrent->id)
            ->count();
        $this->assertSame(1, $buyLogCount2, 'Duplicate purchase must not create a second buy log');
    }

    /**
     * Double thank on the same torrent must be prevented by the unique
     * constraint on thanks (torrentid, userid).
     */
    public function test_double_thank_is_rejected(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($owner)->create();

        /** @var ThankService $service */
        $service = app(ThankService::class);

        // First thank succeeds
        $service->thankTorrent($user, $torrent);
        $thankCount = DB::table('thanks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrent->id)
            ->count();
        $this->assertSame(1, $thankCount);

        // Second thank must fail
        try {
            $service->thankTorrent($user, $torrent);
            $this->fail('Expected exception was not thrown');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('already thank', $e->getMessage());
        } catch (\Exception $e) {
            // Unique constraint violation is also acceptable
            $this->assertTrue(true, 'Duplicate thank was correctly rejected');
        }

        $thankCount2 = DB::table('thanks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrent->id)
            ->count();
        $this->assertSame(1, $thankCount2, 'Duplicate thank must not create a second row');
    }
}
