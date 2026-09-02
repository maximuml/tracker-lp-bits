<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\PasswordRecoveryService;
use App\Services\WebAuthService;
use App\Support\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for PasswordRecoveryService.
 *
 * Covers requestReset (empty email, invalid email, unknown email,
 * pending account, success) and resetPassword (expired/invalid cache
 * entry, nonexistent user, invalid hash, success).
 */
final class PasswordRecoveryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PasswordRecoveryService $service;

    /** @var WebAuthService&MockInterface */
    private WebAuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::table('loginattempts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var WebAuthService&MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->byDefault();
        $authService->shouldReceive('isCaptchaEnabled')->andReturnFalse()->byDefault();
        $authService->shouldReceive('recordFailedAttempt')->byDefault();
        $this->authService = $authService;

        $this->service = new PasswordRecoveryService($this->authService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function createUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => hash('sha256', 'secret'.uniqid()),
            'passhash_algo' => 'sha256',
            'secret' => Token::randomHex(),
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ], $overrides));
    }

    // --- requestReset: empty email ---

    public function test_request_reset_throws_for_empty_email(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service->requestReset(['email' => ''], '127.0.0.1', [], []);
    }

    // --- requestReset: invalid email format ---

    public function test_request_reset_throws_for_invalid_email_format(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service->requestReset(['email' => 'not-an-email'], '127.0.0.1', [], []);
    }

    // --- requestReset: email not in database ---

    public function test_request_reset_throws_for_unknown_email(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service->requestReset(['email' => 'nobody@test.com'], '127.0.0.1', [], []);
    }

    // --- requestReset: pending account ---

    public function test_request_reset_throws_for_pending_account(): void
    {
        $this->createUser([
            'email' => 'pending@test.com',
            'status' => 'pending',
        ]);

        $this->expectException(AuthenticationException::class);

        $this->service->requestReset(['email' => 'pending@test.com'], '127.0.0.1', [], []);
    }

    // --- requestReset: records failed attempt on failures ---

    public function test_request_reset_records_failed_attempt_for_empty_email(): void
    {
        $this->authService->shouldReceive('recordFailedAttempt')
            ->with('127.0.0.1')
            ->once();

        $threw = false;
        try {
            $this->service->requestReset(['email' => ''], '127.0.0.1', [], []);
        } catch (AuthenticationException) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected AuthenticationException to be thrown');
    }

    // --- requestReset: success ---

    public function test_request_reset_succeeds_and_sets_editsecret(): void
    {
        $userId = $this->createUser(['email' => 'valid@test.com']);

        $this->service->requestReset(['email' => 'valid@test.com'], '127.0.0.1', [], []);

        $editsecret = DB::table('users')->where('id', $userId)->value('editsecret');
        $this->assertNotNull($editsecret);
        $this->assertNotSame('', (string) $editsecret);
    }

    public function test_request_reset_stores_hash_in_cache(): void
    {
        $userId = $this->createUser(['email' => 'cache@test.com']);

        $this->service->requestReset(['email' => 'cache@test.com'], '127.0.0.1', [], []);

        // The service stores a random secret in editsecret
        $editSecret = DB::table('users')->where('id', $userId)->value('editsecret');
        $this->assertNotNull($editSecret, 'Expected editsecret to be set on the user');

        // The cache key is recover:<md5(sec+email+passhash+sec)>, not recover:<sec>
        $passhash = DB::table('users')->where('id', $userId)->value('passhash');
        $expectedHash = md5($editSecret.'cache@test.com'.$passhash.$editSecret);
        $cacheHas = CacheFacade::has('recover:'.$expectedHash);
        $this->assertTrue($cacheHas, 'Expected a recover:* key in the cache');
    }

    // --- requestReset: captcha enabled and fails ---

    public function test_request_reset_throws_when_captcha_fails(): void
    {
        $this->authService->shouldReceive('isCaptchaEnabled')->andReturnTrue();
        $this->authService->shouldReceive('recordFailedAttempt')->once();

        $this->expectException(AuthenticationException::class);

        $this->service->requestReset(['email' => 'valid@test.com'], '127.0.0.1', [], []);
    }

    // --- requestReset: banned IP ---

    public function test_request_reset_throws_when_ip_banned(): void
    {
        $this->authService->shouldReceive('assertNotBanned')
            ->andThrow(new AuthenticationException('Your IP is banned.'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Your IP is banned.');

        $this->service->requestReset(['email' => 'valid@test.com'], '127.0.0.1', [], []);
    }

    // --- resetPassword: expired/invalid cache ---

    public function test_reset_password_throws_for_expired_cache_entry(): void
    {
        $userId = $this->createUser();

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, 'invalid_hash', []);
    }

    // --- resetPassword: nonexistent user ---

    public function test_reset_password_throws_for_nonexistent_user(): void
    {
        $hash = md5(uniqid());
        CacheFacade::put("recover:$hash", now()->toDateTimeString(), 3600);

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword(99999, $hash, []);
    }

    // --- resetPassword: invalid hash ---

    public function test_reset_password_throws_for_invalid_hash(): void
    {
        $userId = $this->createUser();
        $editsecret = Token::randomHex();
        DB::table('users')->where('id', $userId)->update(['editsecret' => $editsecret]);

        $validHash = md5($editsecret.'user@test.com'.DB::table('users')->where('id', $userId)->value('passhash').$editsecret);
        CacheFacade::put("recover:$validHash", now()->toDateTimeString(), 3600);

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, 'wrong_hash', []);
    }

    // --- resetPassword: success ---

    public function test_reset_password_succeeds_and_returns_new_password(): void
    {
        $userId = $this->createUser();
        $user = User::query()->find($userId, ['id', 'username', 'email', 'passhash', 'editsecret']);
        $this->assertNotNull($user);

        $editsecret = Token::randomHex();
        $oldPasshash = $user->passhash;
        DB::table('users')->where('id', $userId)->update(['editsecret' => $editsecret]);

        $sec = str_pad($editsecret, 20);
        $hash = md5($sec.$user->email.$oldPasshash.$sec);
        CacheFacade::put("recover:$hash", now()->toDateTimeString(), 3600);

        $newPassword = $this->service->resetPassword($userId, $hash, []);

        $this->assertSame(10, strlen($newPassword));

        $updatedUser = DB::table('users')->where('id', $userId)->first();
        $this->assertNotNull($updatedUser);
        $this->assertNotSame($oldPasshash, $updatedUser->passhash);
        $this->assertSame('', (string) $updatedUser->editsecret);
        $this->assertSame('argon2id', $updatedUser->passhash_algo);
    }

    public function test_reset_password_clears_cache_after_success(): void
    {
        $userId = $this->createUser();
        $user = User::query()->find($userId, ['id', 'username', 'email', 'passhash', 'editsecret']);
        $this->assertNotNull($user);

        $editsecret = Token::randomHex();
        $oldPasshash = $user->passhash;
        DB::table('users')->where('id', $userId)->update(['editsecret' => $editsecret]);

        $sec = str_pad($editsecret, 20);
        $hash = md5($sec.$user->email.$oldPasshash.$sec);
        CacheFacade::put("recover:$hash", now()->toDateTimeString(), 3600);

        $this->service->resetPassword($userId, $hash, []);

        $this->assertNull(CacheFacade::get("recover:$hash"));
    }

    // --- resetPassword: stale editsecret (concurrent reset) ---

    public function test_reset_password_throws_when_editsecret_changed(): void
    {
        $userId = $this->createUser();
        $user = User::query()->find($userId, ['id', 'username', 'email', 'passhash', 'editsecret']);
        $this->assertNotNull($user);

        $editsecret = Token::randomHex();
        $oldPasshash = $user->passhash;
        DB::table('users')->where('id', $userId)->update(['editsecret' => $editsecret]);

        $sec = str_pad($editsecret, 20);
        $hash = md5($sec.$user->email.$oldPasshash.$sec);
        CacheFacade::put("recover:$hash", now()->toDateTimeString(), 3600);

        // Simulate a concurrent reset that changes editsecret
        DB::table('users')->where('id', $userId)->update(['editsecret' => Token::randomHex()]);

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, $hash, []);
    }
}
