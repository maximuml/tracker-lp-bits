<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\PasswordRecoveryService;
use App\Services\SecureTokenService;
use App\Services\WebAuthService;
use App\Support\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for PasswordRecoveryService.
 *
 * Covers requestReset (empty email, invalid email, unknown email,
 * pending account, success) and resetPassword (invalid token,
 * nonexistent user, user ID mismatch, success).
 *
 * W1-04: Legacy md5 token path removed. All tests now use SecureTokenService.
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
        DB::table('password_recovery_tokens')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var WebAuthService&MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->byDefault();
        $authService->shouldReceive('isCaptchaEnabled')->andReturnFalse()->byDefault();
        $authService->shouldReceive('recordFailedAttempt')->byDefault();
        $this->authService = $authService;

        $this->service = new PasswordRecoveryService($this->authService, app(SecureTokenService::class));
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
            'status' => 1,
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
            'status' => 0,
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

    public function test_request_reset_stores_secure_token(): void
    {
        $userId = $this->createUser(['email' => 'token@test.com']);

        $this->service->requestReset(['email' => 'token@test.com'], '127.0.0.1', [], []);

        // W1-04: token digest is stored in password_recovery_tokens
        $tokenRow = DB::table('password_recovery_tokens')
            ->where('user_id', $userId)
            ->first();

        $this->assertNotNull($tokenRow, 'Expected a recovery token row');
        $this->assertSame((int) $userId, (int) $tokenRow->user_id);
        $this->assertNotEmpty($tokenRow->token_digest);
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

    // --- resetPassword: invalid token ---

    public function test_reset_password_throws_for_invalid_token(): void
    {
        $userId = $this->createUser();

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, 'invalid_token', []);
    }

    // --- resetPassword: user ID mismatch ---

    public function test_reset_password_throws_for_user_id_mismatch(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);

        // Generate a token for userId
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        // Try to reset with a different user ID
        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword(99999, $token, []);
    }

    // --- resetPassword: nonexistent user ---

    public function test_reset_password_throws_for_nonexistent_user(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);

        // Generate a token for userId
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        // Delete the user to simulate nonexistent
        DB::table('users')->where('id', $userId)->delete();

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, $token, []);
    }

    // --- resetPassword: success ---

    public function test_reset_password_succeeds_and_returns_new_password(): void
    {
        $userId = $this->createUser();
        $user = User::query()->find($userId, ['id', 'username', 'email', 'passhash', 'editsecret']);
        $this->assertNotNull($user);
        $oldPasshash = $user->passhash;

        // Generate a valid token directly
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $newPassword = $this->service->resetPassword($userId, $token, []);

        $this->assertSame(10, strlen($newPassword));

        $updatedUser = DB::table('users')->where('id', $userId)->first();
        $this->assertNotNull($updatedUser);
        $this->assertNotSame($oldPasshash, $updatedUser->passhash);
        $this->assertSame('', (string) $updatedUser->editsecret);
        $this->assertSame('argon2id', $updatedUser->passhash_algo);
    }

    public function test_reset_password_consumes_token_after_success(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);

        // Generate a token
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        // Reset password
        $this->service->resetPassword($userId, $token, []);

        // Token should be consumed (marked with consumed_at)
        $digest = $tokenService->digest($token);
        $consumedRow = DB::table('password_recovery_tokens')->where('token_digest', $digest)->first();
        $this->assertNotNull($consumedRow);
        $this->assertNotNull($consumedRow->consumed_at, 'Token should be marked as consumed');
    }

    // --- resetPassword: token already consumed (replay attack) ---

    public function test_reset_password_throws_for_already_consumed_token(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);

        // Generate a token
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        // First reset succeeds
        $this->service->resetPassword($userId, $token, []);

        // Second reset with same token should fail
        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, $token, []);
    }
}
