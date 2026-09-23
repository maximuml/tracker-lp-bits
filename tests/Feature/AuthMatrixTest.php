<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\WebAuthService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * SEC-04 auth matrix: every credential path must enforce the same
 * account-state checks, and API rate limiting must not be weaker than
 * the web login.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AuthMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_api_login_rejects_wrong_password_and_records_attempt(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => 'definitely-wrong',
        ])->assertStatus(401);

        $this->assertSame(1, (int) DB::table('loginattempts')
            ->where('ip', '127.0.0.1')
            ->sum('attempts'));
    }

    public function test_api_login_rejects_banned_ip_even_with_valid_password(): void
    {
        $user = User::factory()->create();
        $max = app(WebAuthService::class)->maxLoginAttempts();
        DB::table('loginattempts')->insert([
            'ip' => '127.0.0.1',
            'added' => now()->toDateTimeString(),
            'attempts' => $max,
        ]);

        $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_api_login_rejects_pending_account(): void
    {
        $user = User::factory()->create(['status' => UserStatus::PENDING->value]);

        $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_api_login_rejects_disabled_account(): void
    {
        $user = User::factory()->disabled()->create();

        $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_api_login_rejects_missing_two_step_code(): void
    {
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_api_login_success_issues_unrestricted_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(200);

        $token = (string) $response->json('data.token');
        $this->assertNotSame('', $token);

        // Token works on a normal ability-gated endpoint.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user-me')
            ->assertStatus(200);

        $this->assertSame(1, DB::table('login_logs')->where('uid', $user->id)->where('client', 'API')->count());
    }

    public function test_api_login_flagged_user_gets_limited_token(): void
    {
        $user = User::factory()->create(['must_change_password' => 1]);

        $response = $this->postJson('/api/v1/login', [
            'username' => $user->username,
            'password' => '123456',
        ])->assertStatus(200);

        $token = (string) $response->json('data.token');
        $this->assertTrue((bool) $response->json('data.password_change_required'));

        // Limited token: profile + password-change + logout only.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user-me')
            ->assertStatus(200);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user-publish-torrent')
            ->assertStatus(403);
    }

    public function test_api_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a', ['*'])->plainTextToken;
        $tokenB = $user->createToken('device-b', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/logout')
            ->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());

        // Guards memoize the resolved user per app instance (Octane clears
        // them via FlushAuthenticationState; a test process must do it too).
        $this->app['auth']->forgetGuards();

        // Token A is dead, token B still works.
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/user-me')
            ->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/v1/user-me')
            ->assertStatus(200);
    }

    public function test_api_logout_all_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a', ['*'])->plainTextToken;
        $tokenB = $user->createToken('device-b', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/logout-all')
            ->assertStatus(200);

        foreach ([$tokenA, $tokenB] as $token) {
            $this->app['auth']->forgetGuards();
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/v1/user-me')
                ->assertStatus(401);
        }
    }

    public function test_web_login_still_enforces_banned_ip(): void
    {
        $user = User::factory()->create();
        $max = app(WebAuthService::class)->maxLoginAttempts();
        DB::table('loginattempts')->insert([
            'ip' => '127.0.0.1',
            'added' => now()->toDateTimeString(),
            'attempts' => $max,
        ]);

        // Web login redirects back with an error instead of issuing a cookie.
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => '123456',
        ]);
        $response->assertSessionHas('error');
    }
}
