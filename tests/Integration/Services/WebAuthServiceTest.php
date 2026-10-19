<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\WebAuthService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: account status (pending/disabled) and the TOTP check ran
 * before password verification, so each distinct error message was an
 * oracle — an unauthenticated caller could enumerate account status and
 * probe 2FA codes without knowing the password. The password must be
 * verified first; status/2FA details only after.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class WebAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::table('settings')->updateOrInsert(
            ['name' => 'security.iv'],
            ['value' => 'no'],
        );
        Settings::resetCache();
    }

    private function authenticate(string $username, string $password, string $twoStepCode = ''): string
    {
        try {
            app(WebAuthService::class)->authenticate(
                ['username' => $username, 'password' => $password, 'two_step_code' => $twoStepCode],
                '203.0.113.10',
            );
        } catch (AuthenticationException $e) {
            return $e->getMessage();
        }

        return 'ok';
    }

    public function test_pending_user_with_wrong_password_gets_generic_error(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PENDING->value]);

        $this->assertSame(
            'Username or password invalid.',
            $this->authenticate($user->username, 'wrongpassword'),
        );
    }

    public function test_two_step_user_with_wrong_password_gets_generic_error(): void
    {
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->assertSame(
            'Username or password invalid.',
            $this->authenticate($user->username, 'wrongpassword'),
        );
    }

    public function test_pending_user_with_correct_password_gets_unconfirmed(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PENDING->value]);

        $this->assertSame(
            'Account unconfirmed.',
            $this->authenticate($user->username, '123456'),
        );
    }

    public function test_two_step_user_with_correct_password_gets_code_prompt(): void
    {
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->assertSame(
            'Require two-step code.',
            $this->authenticate($user->username, '123456'),
        );
    }
}
