<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\BusinessType;
use App\Models\User;
use App\Services\BonusLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for the unified bonus ledger: every balance
 * mutation — web or API entry point — goes through the same lock,
 * atomic update, bonus_log write and cache invalidation.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BonusLedgerServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BonusLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(BonusLedgerService::class);
    }

    public function test_debit_deducts_and_logs_locked_balance(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500]);

        $this->ledger->debit($user->id, 100.0, BusinessType::EXCHANGE_UPLOAD, 'test debit');

        $this->assertSame(400.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::EXCHANGE_UPLOAD->value,
            'old_total_value' => 500,
            'value' => 100,
            'new_total_value' => 400,
        ]);
    }

    public function test_debit_throws_and_rolls_back_when_balance_insufficient(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 50]);

        $this->expectException(\LogicException::class);
        try {
            $this->ledger->debit($user->id, 100.0, BusinessType::EXCHANGE_UPLOAD);
        } finally {
            $this->assertSame(50.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
            $this->assertSame(0, DB::table('bonus_logs')->where('uid', $user->id)->count());
        }
    }

    public function test_credit_increments_and_logs(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100]);

        $this->ledger->credit($user->id, 250.0, BusinessType::RECEIVE_GIFT, 'test credit');

        $this->assertSame(350.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::RECEIVE_GIFT->value,
            'old_total_value' => 100,
            'value' => 250,
            'new_total_value' => 350,
        ]);
    }

    public function test_transfer_moves_balance_and_logs_both_sides(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['seedbonus' => 500]);
        /** @var User $receiver */
        $receiver = User::factory()->create(['seedbonus' => 10]);

        $this->ledger->transfer($sender->id, $receiver->id, 200.0, BusinessType::GIFT_TO_SOMEONE, BusinessType::RECEIVE_GIFT);

        $this->assertSame(300.0, (float) DB::table('users')->where('id', $sender->id)->value('seedbonus'));
        $this->assertSame(210.0, (float) DB::table('users')->where('id', $receiver->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $sender->id,
            'business_type' => BusinessType::GIFT_TO_SOMEONE->value,
            'old_total_value' => 500,
            'new_total_value' => 300,
        ]);
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $receiver->id,
            'business_type' => BusinessType::RECEIVE_GIFT->value,
            'old_total_value' => 10,
            'new_total_value' => 210,
        ]);
    }

    public function test_transfer_supports_asymmetric_credit_amount(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['seedbonus' => 500]);
        /** @var User $receiver */
        $receiver = User::factory()->create(['seedbonus' => 0]);

        $this->ledger->transfer($sender->id, $receiver->id, 100.0, BusinessType::GIFT_TO_SOMEONE, BusinessType::RECEIVE_GIFT, 90.0);

        $this->assertSame(400.0, (float) DB::table('users')->where('id', $sender->id)->value('seedbonus'));
        $this->assertSame(90.0, (float) DB::table('users')->where('id', $receiver->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $receiver->id,
            'business_type' => BusinessType::RECEIVE_GIFT->value,
            'value' => 90,
            'new_total_value' => 90,
        ]);
    }

    public function test_transfer_rolls_back_credit_when_debit_fails(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['seedbonus' => 10]);
        /** @var User $receiver */
        $receiver = User::factory()->create(['seedbonus' => 5]);

        $this->expectException(\LogicException::class);
        try {
            $this->ledger->transfer($sender->id, $receiver->id, 500.0, BusinessType::GIFT_TO_SOMEONE, BusinessType::RECEIVE_GIFT);
        } finally {
            $this->assertSame(10.0, (float) DB::table('users')->where('id', $sender->id)->value('seedbonus'));
            $this->assertSame(5.0, (float) DB::table('users')->where('id', $receiver->id)->value('seedbonus'));
            $this->assertSame(0, DB::table('bonus_logs')->whereIn('uid', [$sender->id, $receiver->id])->count());
        }
    }

    public function test_transfer_rejects_self_transfer(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500]);

        $this->expectException(\LogicException::class);
        $this->ledger->transfer($user->id, $user->id, 100.0, BusinessType::GIFT_TO_SOMEONE, BusinessType::RECEIVE_GIFT);
    }

    public function test_transfer_rejects_missing_recipient(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['seedbonus' => 500]);

        $this->expectException(\LogicException::class);
        $this->ledger->transfer($sender->id, PHP_INT_MAX, 100.0, BusinessType::GIFT_TO_SOMEONE, BusinessType::RECEIVE_GIFT);
    }

    public function test_credit_throws_on_missing_user(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->ledger->credit(PHP_INT_MAX, 10.0, BusinessType::RECEIVE_GIFT);
    }

    public function test_award_silently_skips_missing_user(): void
    {
        $this->ledger->award(PHP_INT_MAX, 10.0, BusinessType::SAY_THANKS, 'ghost user');

        $this->assertSame(0, DB::table('bonus_logs')->where('uid', PHP_INT_MAX)->count());
    }

    public function test_award_credits_balance_and_writes_log(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100]);

        $this->ledger->award($user->id, 50.0, BusinessType::SAY_THANKS, 'thanks');

        $this->assertSame(150.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::SAY_THANKS->value,
            'old_total_value' => 100,
            'value' => 50,
            'new_total_value' => 150,
        ]);
    }

    public function test_award_respects_explicit_tweak_gate(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100]);

        $this->ledger->award($user->id, 50.0, BusinessType::SAY_THANKS, 'gated', bonusTweak: 'no');

        $this->assertSame(100.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertSame(0, DB::table('bonus_logs')->where('uid', $user->id)->count());
    }

    public function test_award_credits_under_disablesave_tweak(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100]);

        $this->ledger->award($user->id, 25.0, BusinessType::FORUM_POST_BONUS, 'post', bonusTweak: 'disablesave');

        $this->assertSame(125.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::FORUM_POST_BONUS->value,
            'new_total_value' => 125,
        ]);
    }

    public function test_revoke_allows_negative_balance_and_logs(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 10]);

        $this->ledger->revoke($user->id, 40.0, BusinessType::BONUS_REVERSAL, 'clawback');

        $this->assertSame(-30.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'business_type' => BusinessType::BONUS_REVERSAL->value,
            'old_total_value' => 10,
            'value' => 40,
            'new_total_value' => -30,
        ]);
    }

    public function test_revoke_skips_when_gated_off(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 10]);

        $this->ledger->revoke($user->id, 5.0, BusinessType::BONUS_REVERSAL, 'gated', bonusTweak: 'no');

        $this->assertSame(10.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertSame(0, DB::table('bonus_logs')->where('uid', $user->id)->count());
    }

    public function test_award_credits_fractional_amounts(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 0]);

        $this->ledger->award($user->id, 0.5, BusinessType::SAY_THANKS, 'half point');

        $this->assertSame(0.5, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $user->id,
            'value' => 0.5,
            'new_total_value' => 0.5,
        ]);
    }
}
