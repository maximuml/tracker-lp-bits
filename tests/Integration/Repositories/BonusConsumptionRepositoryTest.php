<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\BusinessType;
use App\Models\BonusLogs;
use App\Models\User;
use App\Repositories\BonusConsumptionRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class BonusConsumptionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BonusConsumptionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(BonusConsumptionRepository::class);
    }

    public function test_consume_user_bonus_debits_balance_and_writes_log(): void
    {
        $user = User::factory()->create(['seedbonus' => 200.0]);

        $this->repository->consumeUserBonus(
            $user->id,
            50.0,
            BusinessType::EXCHANGE_UPLOAD->value,
            'test consume'
        );

        $this->assertSame(150.0, (float) User::query()->where('id', $user->id)->value('seedbonus'));

        $log = BonusLogs::query()->where('uid', $user->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(BusinessType::EXCHANGE_UPLOAD->value, (int) $log->business_type);
        $this->assertSame(200.0, (float) $log->old_total_value);
        $this->assertSame(50.0, (float) $log->value);
        $this->assertSame(150.0, (float) $log->new_total_value);
    }

    public function test_consume_user_bonus_throws_when_insufficient(): void
    {
        $user = User::factory()->create(['seedbonus' => 10.0]);

        $this->expectException(\LogicException::class);

        try {
            $this->repository->consumeUserBonus($user->id, 100.0, BusinessType::EXCHANGE_UPLOAD->value);
        } finally {
            // balance unchanged
            $this->assertSame(10.0, (float) User::query()->where('id', $user->id)->value('seedbonus'));
        }
    }

    public function test_consume_user_bonus_zero_amount_is_noop(): void
    {
        $user = User::factory()->create(['seedbonus' => 42.0]);

        $this->repository->consumeUserBonus($user->id, 0.0, BusinessType::EXCHANGE_UPLOAD->value);

        $this->assertSame(42.0, (float) User::query()->where('id', $user->id)->value('seedbonus'));
        $this->assertFalse(BonusLogs::query()->where('uid', $user->id)->exists());
    }

    public function test_consume_user_bonus_rejects_reserved_user_update_fields(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0]);

        $this->expectException(\InvalidArgumentException::class);

        $this->repository->consumeUserBonus(
            $user->id,
            10.0,
            BusinessType::EXCHANGE_UPLOAD->value,
            '',
            ['seedbonus' => 999]
        );
    }

    public function test_consume_user_bonus_applies_extra_user_updates(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0, 'invites' => 0]);

        $this->repository->consumeUserBonus(
            $user->id,
            30.0,
            BusinessType::EXCHANGE_INVITE->value,
            'buy invite',
            ['invites' => 1]
        );

        $user->refresh();
        $this->assertSame(70.0, (float) $user->seedbonus);
        $this->assertSame(1, (int) $user->invites);
    }

    public function test_consume_and_increment_charity(): void
    {
        $user = User::factory()->create(['seedbonus' => 100.0, 'charity' => 0]);

        $this->repository->consumeUserBonusAndIncrementCharity(
            $user->id,
            40.0,
            BusinessType::GIFT_TO_LOW_SHARE_RATIO->value,
            'charity gift',
            40.0
        );

        $user->refresh();
        $this->assertSame(60.0, (float) $user->seedbonus);
        $this->assertSame(40.0, (float) $user->charity);
    }

    public function test_increment_seedbonus_for_low_ratio_receivers_only_hits_eligible_users(): void
    {
        $eligible = User::factory()->create([
            'seedbonus' => 10.0,
            'enabled' => true,
            'uploaded' => 5 * 1073741824,
            'downloaded' => 20 * 1073741824,
        ]);
        $highRatio = User::factory()->create([
            'seedbonus' => 10.0,
            'enabled' => true,
            'uploaded' => 20 * 1073741824,
            'downloaded' => 1073741824,
        ]);
        $disabled = User::factory()->create([
            'seedbonus' => 10.0,
            'enabled' => false,
            'uploaded' => 5 * 1073741824,
            'downloaded' => 20 * 1073741824,
        ]);

        $affected = $this->repository->incrementSeedbonusForLowRatioReceivers(1.0, 50.0);

        $this->assertSame(50.0 + 10.0, (float) User::query()->where('id', $eligible->id)->value('seedbonus'));
        $this->assertSame(10.0, (float) User::query()->where('id', $highRatio->id)->value('seedbonus'));
        $this->assertSame(10.0, (float) User::query()->where('id', $disabled->id)->value('seedbonus'));
        $this->assertGreaterThanOrEqual(1, $affected);
    }
}
