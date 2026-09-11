<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\User;
use App\Support\PasswordHasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Testing\PendingCommand;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class UsersLegacyHashReportCommandTest extends TestCase
{
    public function test_reports_counts_per_algorithm(): void
    {
        User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);
        User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_SHA256]);
        User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_ARGON2ID]);

        $legacy = User::query()
            ->where(fn (Builder $q) => $q->where('passhash_algo', '!=', PasswordHasher::ALGO_ARGON2ID)
                ->orWhereNull('passhash_algo')
                ->orWhere('passhash_algo', ''))
            ->count();

        $command = $this->artisan('users:legacy-hash-report');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('passhash_algo')
            ->expectsOutputToContain(PasswordHasher::ALGO_MD5)
            ->expectsOutputToContain(sprintf('Legacy (non-%s): %d', PasswordHasher::ALGO_ARGON2ID, $legacy))
            ->assertExitCode(0);
    }

    public function test_list_option_filters_by_algorithm(): void
    {
        $md5User = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_MD5]);
        $argonUser = User::factory()->create(['passhash_algo' => PasswordHasher::ALGO_ARGON2ID]);

        $command = $this->artisan('users:legacy-hash-report', ['--list' => PasswordHasher::ALGO_MD5]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain((string) $md5User->username)
            ->doesntExpectOutputToContain((string) $argonUser->username)
            ->assertExitCode(0);
    }
}
