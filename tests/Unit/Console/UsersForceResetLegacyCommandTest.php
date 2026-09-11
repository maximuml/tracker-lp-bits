<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\User;
use App\Support\PasswordHasher;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class UsersForceResetLegacyCommandTest extends TestCase
{
    public function test_dry_run_does_not_flag_users(): void
    {
        $md5User = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);

        $this->artisan('users:force-reset-legacy', ['--force' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertFalse((bool) $md5User->fresh()->must_change_password);
    }

    public function test_apply_flags_legacy_users(): void
    {
        $md5User = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);
        $argonUser = User::factory()->create([
            'passhash' => PasswordHasher::hash('pw'),
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
        ]);

        $this->artisan('users:force-reset-legacy', ['--apply' => true, '--force' => true])
            ->assertExitCode(0);

        $this->assertTrue((bool) $md5User->fresh()->must_change_password);
        $this->assertFalse((bool) $argonUser->fresh()->must_change_password);
    }

    public function test_threshold_guard_refuses_without_force(): void
    {
        $md5User = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);

        $this->artisan('users:force-reset-legacy', ['--apply' => true, '--threshold' => 0])
            ->expectsOutputToContain('threshold')
            ->assertExitCode(1);

        $this->assertFalse((bool) $md5User->fresh()->must_change_password);
    }
}
