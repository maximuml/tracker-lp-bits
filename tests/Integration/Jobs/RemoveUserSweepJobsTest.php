<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Enums\UserClass;
use App\Jobs\RemoveUserDonorStatus;
use App\Jobs\RemoveUserVipStatus;
use App\Jobs\RemoveUserWarning;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Repositories\UserDetailRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * @covers \App\Jobs\RemoveUserWarning
 * @covers \App\Jobs\RemoveUserVipStatus
 * @covers \App\Jobs\RemoveUserDonorStatus
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class RemoveUserSweepJobsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->delete();
        DB::table('user_modify_logs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        Model::reguard();
        parent::tearDown();
    }

    public function test_remove_user_warning_clears_flag_and_stays_idempotent(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'enabled' => 1,
            'warned' => 1,
            'warneduntil' => now()->subHour(),
        ]);

        $job = new RemoveUserWarning;
        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));

        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('warned'));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());

        // An overlapping/late second run finds nothing to claim — the
        // message and modify log must not be duplicated.
        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());
    }

    public function test_remove_user_warning_leaves_active_warning_alone(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'enabled' => 1,
            'warned' => 1,
            'warneduntil' => now()->addDay(),
        ]);

        (new RemoveUserWarning)->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));

        $this->assertSame(1, (int) DB::table('users')->where('id', $user->id)->value('warned'));
        $this->assertSame(0, DB::table('messages')->where('receiver', $user->id)->count());
    }

    public function test_remove_user_vip_status_downgrades_class_once(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'vip_added' => 1,
            'vip_until' => now()->subHour(),
            'class' => UserClass::VIP->value,
        ]);

        $job = new RemoveUserVipStatus;
        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(0, (int) $row->vip_added);
        $this->assertSame(UserClass::USER->value, (int) $row->class);
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());

        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());
    }

    public function test_remove_user_donor_status_clears_flag_once(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'donor' => 1,
            'donoruntil' => now()->subHour(),
        ]);

        $job = new RemoveUserDonorStatus;
        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));

        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('donor'));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());

        $job->handle(app(UserDetailRepository::class), app(UserCleanupRepository::class), app(MessageRepository::class));
        $this->assertSame(1, DB::table('messages')->where('receiver', $user->id)->count());
        $this->assertSame(1, DB::table('user_modify_logs')->where('user_id', $user->id)->count());
    }
}
