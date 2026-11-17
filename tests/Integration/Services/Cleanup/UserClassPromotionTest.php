<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Cleanup;

use App\Enums\UserClass;
use App\Services\Cleanup\UserClassPromotion;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pin test for the reduced auto-promotion ladder: promotions follow
 * UserClass::autoPromotable() (User → Power → Elite → Veteran →
 * Ultimate) instead of the numeric class±1 chain, and retired tiers
 * (Crazy/Insane/Extreme/Nexus Master) are no longer reachable.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserClassPromotionTest extends TestCase
{
    private UserClassPromotion $promotion;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
        $this->promotion = app(UserClassPromotion::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_elite_user_promotes_to_veteran_on_the_new_chain(): void
    {
        $this->setVeteranPromotionSettings();

        $uid = $this->insertUser([
            'class' => UserClass::ELITE_USER->value,
            'max_class_once' => UserClass::ELITE_USER->value,
            'downloaded' => 2 * 1024 * 1024 * 1024,
            'uploaded' => 1024 * 1024 * 1024,
            'seed_points' => 0,
        ]);

        $this->promotion->promoteUsersByClass();

        $user = DB::table('users')->where('id', $uid)->first();
        $this->assertNotNull($user);
        $this->assertSame(UserClass::VETERAN_USER->value, (int) $user->class);
        $this->assertSame(UserClass::VETERAN_USER->value, (int) $user->max_class_once);
    }

    public function test_retired_tier_is_not_a_promotion_candidate(): void
    {
        $this->setVeteranPromotionSettings();

        // Veteran promotion sources Elite users on the new chain; a user
        // stuck on retired Insane (5) cannot jump into the ladder.
        $uid = $this->insertUser([
            'class' => UserClass::INSANE_USER->value,
            'max_class_once' => UserClass::INSANE_USER->value,
            'downloaded' => 10 * 1024 * 1024 * 1024,
            'uploaded' => 10 * 1024 * 1024 * 1024,
            'seed_points' => 999999,
        ]);

        $this->promotion->promoteUsersByClass();

        $this->assertSame(
            UserClass::INSANE_USER->value,
            (int) DB::table('users')->where('id', $uid)->value('class'),
        );
    }

    private function setVeteranPromotionSettings(): void
    {
        foreach ([
            'account.vutime' => '0',
            'account.vudl' => '1',
            'account.vuprratio' => '0.5',
            'account.6_min_seed_points' => '0',
        ] as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'autoload' => 1],
            );
        }

        Settings::resetCache();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertUser(array $attributes): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'promo-'.bin2hex(random_bytes(4)),
            'email' => bin2hex(random_bytes(4)).'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => bin2hex(random_bytes(16)),
            'enabled' => 1,
            'invites' => 0,
            'added' => now()->subYears(2)->toDateTimeString(),
        ], $attributes));
    }
}
