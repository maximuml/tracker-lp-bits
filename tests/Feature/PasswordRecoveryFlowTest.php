<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\NexusWebUserProvider;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Services\SecureTokenService;
use App\Support\AuthCookie;
use App\Support\PasswordHasher;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class PasswordRecoveryFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_reset_link_shows_form_without_consuming_token_or_changing_password(): void
    {
        $user = User::factory()->create();
        $token = $this->storeToken((int) $user->id);
        $oldPasshash = (string) $user->passhash;

        $response = $this->get('/recover?'.http_build_query([
            'id' => (int) $user->id,
            'secret' => $token,
        ]));

        $response->assertOk();
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertHeader('referrer-policy', 'no-referrer');
        $response->assertHeader('x-robots-tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        $tokenRow = DB::table('password_recovery_tokens')
            ->where('token_digest', app(SecureTokenService::class)->digest($token))
            ->first();
        $this->assertNotNull($tokenRow);
        $this->assertNull($tokenRow->consumed_at);
        $this->assertSame(0, (int) $tokenRow->revoked);
        $this->assertSame($oldPasshash, (string) $user->fresh()?->passhash);
    }

    public function test_legacy_recover_php_link_shows_reset_form_without_consuming_token(): void
    {
        $user = User::factory()->create();
        $token = $this->storeToken((int) $user->id);

        $response = $this->get('/recover.php?'.http_build_query([
            'id' => (int) $user->id,
            'secret' => $token,
        ]));

        $response->assertOk();
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertHeader('referrer-policy', 'no-referrer');

        $tokenRow = DB::table('password_recovery_tokens')
            ->where('token_digest', app(SecureTokenService::class)->digest($token))
            ->first();
        $this->assertNotNull($tokenRow);
        $this->assertNull($tokenRow->consumed_at);
        $this->assertSame(0, (int) $tokenRow->revoked);
    }

    public function test_post_reset_uses_chosen_password_and_revokes_existing_cookie(): void
    {
        $user = User::factory()->create();
        $token = $this->storeToken((int) $user->id);
        $oldCookie = [
            AuthCookie::COOKIE_NAME => AuthCookie::buildToken(
                (int) $user->id,
                null,
                time() + 3600,
                (int) $user->auth_version,
            ),
        ];
        $provider = app(NexusWebUserProvider::class);
        $this->assertInstanceOf(User::class, $provider->retrieveByCredentials($oldCookie));

        $response = $this->withSession(['_token' => 'sec-02-token'])
            ->post('/recover/reset', [
                '_token' => 'sec-02-token',
                'id' => (int) $user->id,
                'secret' => $token,
                'password' => 'ChosenPass123',
                'password_confirmation' => 'ChosenPass123',
            ]);

        $response->assertRedirect('/login?status=reset');
        $this->get('/login?status=reset')
            ->assertOk()
            ->assertSee('Your password has been changed. You can now log in.', false)
            ->assertDontSee('check your email for the new password', false);
        $freshUser = $user->fresh();
        $this->assertInstanceOf(User::class, $freshUser);
        $this->assertTrue(PasswordHasher::verify(
            'ChosenPass123',
            (string) $freshUser->passhash,
            (string) $freshUser->secret,
            (string) $freshUser->passhash_algo,
        ));
        $this->assertSame((int) $user->auth_version + 1, (int) $freshUser->auth_version);
        $this->assertNull($provider->retrieveByCredentials($oldCookie));

        $tokenRow = DB::table('password_recovery_tokens')
            ->where('token_digest', app(SecureTokenService::class)->digest($token))
            ->first();
        $this->assertNotNull($tokenRow);
        $this->assertNotNull($tokenRow->consumed_at);
    }

    public function test_post_reset_password_mismatch_returns_to_link_without_consuming_token(): void
    {
        $user = User::factory()->create();
        $token = $this->storeToken((int) $user->id);
        $oldPasshash = (string) $user->passhash;

        $response = $this->withSession(['_token' => 'sec-02-token'])
            ->post('/recover/reset', [
                '_token' => 'sec-02-token',
                'id' => (int) $user->id,
                'secret' => $token,
                'password' => 'ChosenPass123',
                'password_confirmation' => 'DifferentPass123',
            ]);

        $response->assertRedirect('/recover?'.http_build_query([
            'id' => (int) $user->id,
            'secret' => $token,
        ]));
        $response->assertHeader('referrer-policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $tokenRow = DB::table('password_recovery_tokens')
            ->where('token_digest', app(SecureTokenService::class)->digest($token))
            ->first();
        $this->assertNotNull($tokenRow);
        $this->assertNull($tokenRow->consumed_at);
        $this->assertSame(0, (int) $tokenRow->revoked);
        $this->assertSame($oldPasshash, (string) $user->fresh()?->passhash);
    }

    public function test_post_reset_requires_csrf(): void
    {
        $request = Request::create('/recover/reset', 'POST');
        $route = app('router')->getRoutes()->match($request);

        $this->assertContains('web', $route->gatherMiddleware());

        $middleware = app(VerifyCsrfToken::class);
        $inExceptArray = new \ReflectionMethod(VerifyCsrfToken::class, 'inExceptArray');
        $inExceptArray->setAccessible(true);

        $this->assertFalse($inExceptArray->invoke($middleware, $request));
    }

    public function test_recovery_request_response_is_neutral_for_known_and_unknown_email(): void
    {
        $user = User::factory()->create();
        Settings::saveBatch('security', ['iv' => '']);
        Settings::saveBatch('smtp', ['smtptype' => 'none']);
        Settings::resetCache();

        try {
            Redis::connection()->client()->del('nexus_settings_in_nexus', 'nexus_settings_in_laravel');

            $unknown = $this->withSession(['_token' => 'sec-02-token'])
                ->post('/recover', [
                    '_token' => 'sec-02-token',
                    'email' => 'missing@example.com',
                ]);
            $known = $this->withSession(['_token' => 'sec-02-token'])
                ->post('/recover', [
                    '_token' => 'sec-02-token',
                    'email' => (string) $user->email,
                ]);

            $unknown->assertRedirect('/recover?status=requested');
            $known->assertRedirect('/recover?status=requested');
            $this->assertSame(
                $unknown->headers->get('Location'),
                $known->headers->get('Location'),
            );
        } finally {
            Redis::connection()->client()->del('nexus_settings_in_nexus', 'nexus_settings_in_laravel');
            Settings::resetCache();
        }
    }

    public function test_invalid_reset_link_shows_error_and_keeps_request_form(): void
    {
        foreach (['/recover?id=1&secret=invalid-token', '/recover?id=0&secret='.str_repeat('a', 64), '/recover?secret='.str_repeat('a', 64)] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('name="email"', false);
            $response->assertDontSee('name="password"', false);
            $response->assertSee('invalid, expired, or already used', false);
            $response->assertHeader('referrer-policy', 'no-referrer');
        }
    }

    public function test_expired_reset_link_shows_error_and_keeps_request_form(): void
    {
        $user = User::factory()->create();
        $token = $this->storeToken((int) $user->id);
        DB::table('password_recovery_tokens')
            ->where('token_digest', app(SecureTokenService::class)->digest($token))
            ->update(['expires_at' => now()->subMinute()->toDateTimeString()]);

        $response = $this->get('/recover?'.http_build_query([
            'id' => (int) $user->id,
            'secret' => $token,
        ]));

        $response->assertOk();
        $response->assertSee('name="email"', false);
        $response->assertDontSee('name="password"', false);
        $response->assertSee('invalid, expired, or already used', false);
    }

    private function storeToken(int $userId): string
    {
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        return $token;
    }
}
