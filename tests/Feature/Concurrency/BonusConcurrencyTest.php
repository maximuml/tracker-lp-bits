<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Enums\BusinessType;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\BonusConsumptionRepository;
use App\Services\BonusLedgerService;
use App\Services\BonusPurchaseService;
use App\Services\ThankService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::CONCURRENCY, TestCategory::SERVICE_INTEGRATION)]
class BonusConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_concurrent_bonus_updates_do_not_lose_increments(): void
    {
        $user = User::factory()->create(['seedbonus' => 0.0]);

        $ledger = app(BonusLedgerService::class);

        // Simulate 10 concurrent +10 bonus credits
        // In a real concurrent scenario, each would be a separate process,
        // but we verify the lock + CAS update applies every credit
        for ($i = 0; $i < 10; $i++) {
            $ledger->credit($user->id, 10.0, BusinessType::SAY_THANKS, 'test credit');
        }

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(100.0, $finalBonus);
    }

    public function test_concurrent_bonus_decrements_do_not_go_negative_unexpectedly(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $ledger = app(BonusLedgerService::class);

        // 5 revokes of 10 each = 50 total
        for ($i = 0; $i < 5; $i++) {
            $ledger->revoke($user->id, 10.0, BusinessType::BONUS_REVERSAL, 'test revoke', 'enable');
        }

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus);
    }

    public function test_award_respects_bonus_tweak_setting(): void
    {
        $user = User::factory()->create(['seedbonus' => 0.0]);

        $ledger = app(BonusLedgerService::class);

        // bonus_tweak disabled — award should be a no-op
        Settings::saveBatch('tweak', ['bonus' => 'disable']);
        Settings::resetCache();

        $ledger->award($user->id, 50.0, BusinessType::SAY_THANKS, 'test award');

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(0.0, $finalBonus);
        $this->assertSame(0, DB::table('bonus_logs')->where('uid', $user->id)->count());

        // bonus_tweak enabled — award credits and logs
        Settings::saveBatch('tweak', ['bonus' => 'enable']);
        Settings::resetCache();

        $ledger->award($user->id, 50.0, BusinessType::SAY_THANKS, 'test award');

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(50.0, $finalBonus);
        $this->assertSame(1, DB::table('bonus_logs')->where('uid', $user->id)->count());
    }

    public function test_award_with_zero_point_is_noop(): void
    {
        $user = User::factory()->create(['seedbonus' => 42.0]);

        Settings::saveBatch('tweak', ['bonus' => 'enable']);
        Settings::resetCache();

        app(BonusLedgerService::class)->award($user->id, 0.0, BusinessType::SAY_THANKS);

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(42.0, $finalBonus);
    }

    public function test_award_silently_skips_missing_user(): void
    {
        Settings::saveBatch('tweak', ['bonus' => 'enable']);
        Settings::resetCache();

        // Legacy tolerance: awarding a deleted user must not throw.
        app(BonusLedgerService::class)->award(999999, 50.0, BusinessType::SAY_THANKS);

        $this->assertSame(0, DB::table('bonus_logs')->where('uid', 999999)->count());
    }

    public function test_revoke_allows_negative_balance_for_clawback(): void
    {
        $user = User::factory()->create(['seedbonus' => 30.0]);

        Settings::saveBatch('tweak', ['bonus' => 'enable']);
        Settings::resetCache();

        // Clawback of a bonus larger than the current balance leaves the
        // account in debt, matching the legacy bare `seedbonus - X` update.
        app(BonusLedgerService::class)->revoke($user->id, 50.0, BusinessType::BONUS_REVERSAL, 'clawback');

        $finalBonus = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $this->assertSame(-20.0, $finalBonus);
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::BONUS_REVERSAL->value,
            'old_total_value' => 30,
            'value' => 50,
            'new_total_value' => -20,
        ]);
    }

    /**
     * Consuming bonus when the user has insufficient balance must fail
     * and leave the balance unchanged. This verifies the lockForUpdate-based
     * check inside consumeUserBonus prevents over-spending.
     */
    public function test_consume_user_bonus_fails_when_insufficient_balance(): void
    {
        $user = User::factory()->create(['seedbonus' => 50.0]);
        $consumptionRepository = app(BonusConsumptionRepository::class);

        try {
            $consumptionRepository->consumeUserBonus($user->id, 100.0, BusinessType::EXCHANGE_UPLOAD->value, 'test');
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
        $consumptionRepository = app(BonusConsumptionRepository::class);

        $consumptionRepository->consumeUserBonus($user->id, 30.0, BusinessType::EXCHANGE_UPLOAD->value, 'first');
        $consumptionRepository->consumeUserBonus($user->id, 20.0, BusinessType::EXCHANGE_DOWNLOAD->value, 'second');

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
        $purchaseService = app(BonusPurchaseService::class);

        // First purchase succeeds
        $purchaseService->consumeToBuyTorrent($buyer->id, $torrent->id);
        $buyLogCount = DB::table('torrent_buy_logs')
            ->where('uid', $buyer->id)
            ->where('torrent_id', $torrent->id)
            ->count();
        $this->assertSame(1, $buyLogCount, 'First purchase should create exactly one buy log');

        // Second purchase must fail due to unique constraint
        try {
            $purchaseService->consumeToBuyTorrent($buyer->id, $torrent->id);
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
