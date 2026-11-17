<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Cleanup\Tasks;

use App\Enums\UserClass;
use App\Services\Cleanup\Tasks\UserClassManagementTask;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pin test for the reduced auto-promotion ladder on the demotion side:
 * demotions follow UserClass::autoPredecessor() (… → Veteran → Elite →
 * Power → User) instead of class-1, and retired tiers are skipped.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserClassManagementTaskTest extends TestCase
{
    private UserClassManagementTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
        $this->task = app(UserClassManagementTask::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_veteran_demotes_to_elite_on_the_new_chain(): void
    {
        $this->setVeteranDemotionSettings();

        $uid = $this->insertUser([
            'class' => UserClass::VETERAN_USER->value,
            'downloaded' => 2 * 1024 * 1024 * 1024,
            'uploaded' => 1024 * 1024 * 1024,
        ]);

        $this->task->manageUserClasses();

        // Was class - 1 = Insane (5) on the old ladder; now Elite (3).
        $this->assertSame(
            UserClass::ELITE_USER->value,
            (int) DB::table('users')->where('id', $uid)->value('class'),
        );
    }

    public function test_retired_tier_is_not_demoted(): void
    {
        $this->setVeteranDemotionSettings();

        $uid = $this->insertUser([
            'class' => UserClass::INSANE_USER->value,
            'downloaded' => 2 * 1024 * 1024 * 1024,
            'uploaded' => 1024 * 1024 * 1024,
        ]);

        $this->task->manageUserClasses();

        $this->assertSame(
            UserClass::INSANE_USER->value,
            (int) DB::table('users')->where('id', $uid)->value('class'),
        );
    }

    private function setVeteranDemotionSettings(): void
    {
        foreach ([
            'account.vuderatio' => '2.0',
            'account.euderatio' => '0',
            'account.puderatio' => '0',
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
            'username' => 'demo-'.bin2hex(random_bytes(4)),
            'email' => bin2hex(random_bytes(4)).'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => bin2hex(random_bytes(16)),
            'enabled' => 1,
            'leechwarn' => 0,
            'added' => now()->subYears(2)->toDateTimeString(),
        ], $attributes));
    }
}
