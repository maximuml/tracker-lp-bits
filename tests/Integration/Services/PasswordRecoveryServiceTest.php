<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\AuthenticationException;
use App\Jobs\SendLegacyMail;
use App\Models\User;
use App\Repositories\UserAccountRepository;
use App\Repositories\UserDetailRepository;
use App\Services\OutboxService;
use App\Services\PasswordRecoveryService;
use App\Services\PasswordSetup;
use App\Services\SecureTokenService;
use App\Services\WebAuthService;
use App\Support\Config\SiteConfig;
use App\Support\PasswordHasher;
use App\Support\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
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
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
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
        DB::table('users')->delete();
        DB::table('loginattempts')->delete();
        DB::table('password_recovery_tokens')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var WebAuthService&MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->byDefault();
        $authService->shouldReceive('isCaptchaEnabled')->andReturnFalse()->byDefault();
        $authService->shouldReceive('recordFailedAttempt')->byDefault();
        $this->authService = $authService;

        $this->service = new PasswordRecoveryService(
            new UserDetailRepository,
            $this->authService,
            app(SecureTokenService::class),
            app(PasswordSetup::class),
            new UserAccountRepository,
            app(OutboxService::class),
        );
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

    public function test_request_reset_succeeds_silently_for_unknown_email(): void
    {
        // Silently succeed so the endpoint cannot be used to enumerate registered emails.
        $this->authService->shouldReceive('recordFailedAttempt')->with('127.0.0.1')->once();

        $this->service->requestReset(['email' => 'nobody@test.com'], '127.0.0.1', [], []);

        $this->assertSame(0, DB::table('password_recovery_tokens')->count());
    }

    // --- requestReset: pending account ---

    public function test_request_reset_succeeds_silently_for_pending_account(): void
    {
        $userId = $this->createUser([
            'email' => 'pending@test.com',
            'status' => 0,
        ]);

        $this->authService->shouldReceive('recordFailedAttempt')->with('127.0.0.1')->once();

        $this->service->requestReset(['email' => 'pending@test.com'], '127.0.0.1', [], []);

        $this->assertSame(
            0,
            DB::table('password_recovery_tokens')->where('user_id', $userId)->count()
        );
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

    public function test_request_reset_succeeds_without_touching_editsecret(): void
    {
        $userId = $this->createUser([
            'email' => 'valid@test.com',
            'editsecret' => 'email-change-token',
        ]);

        $this->service->requestReset(['email' => 'valid@test.com'], '127.0.0.1', [], []);

        $editsecret = DB::table('users')->where('id', $userId)->value('editsecret');
        $this->assertSame('email-change-token', $editsecret);
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

    public function test_request_reset_revokes_previous_active_tokens(): void
    {
        $userId = $this->createUser(['email' => 'latest@test.com']);
        $tokenService = app(SecureTokenService::class);
        $oldToken = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $oldToken, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $this->service->requestReset(['email' => 'latest@test.com'], '127.0.0.1', [], []);

        $oldRow = DB::table('password_recovery_tokens')
            ->where('token_digest', $tokenService->digest($oldToken))
            ->first();
        $this->assertNotNull($oldRow);
        $this->assertSame(1, (int) $oldRow->revoked);
        $this->assertNull($this->service->validateResetToken($userId, $oldToken));
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

    // --- validateResetToken: form display must not consume ---

    public function test_validate_reset_token_returns_row_without_consuming_it(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $row = $this->service->validateResetToken($userId, $token);

        $this->assertNotNull($row);
        $stored = DB::table('password_recovery_tokens')
            ->where('token_digest', $tokenService->digest($token))
            ->first();
        $this->assertNotNull($stored);
        $this->assertNull($stored->consumed_at);
        $this->assertSame(0, (int) $stored->revoked);
    }

    // --- resetPassword: invalid token ---

    public function test_reset_password_throws_for_invalid_token(): void
    {
        $userId = $this->createUser();

        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, 'invalid_token', 'NewPass123', 'NewPass123');
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

        // Try to reset with a different user ID; the token must remain unconsumed.
        try {
            $this->service->resetPassword(99999, $token, 'NewPass123', 'NewPass123');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            $row = DB::table('password_recovery_tokens')
                ->where('token_digest', $tokenService->digest($token))
                ->first();
            $this->assertNotNull($row);
            $this->assertNull($row->consumed_at);
            $this->assertSame(0, (int) $row->revoked);
        }
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

        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');
    }

    // --- resetPassword: success ---

    public function test_reset_password_uses_submitted_password(): void
    {
        $userId = $this->createUser();
        $user = User::query()->find($userId, ['id', 'username', 'email', 'passhash', 'editsecret', 'auth_version']);
        $this->assertNotNull($user);
        $oldPasshash = $user->passhash;
        $oldAuthVersion = (int) $user->auth_version;

        // Generate a valid token directly
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $this->service->resetPassword($userId, $token, 'ChosenPass123', 'ChosenPass123');

        $updatedUser = DB::table('users')->where('id', $userId)->first();
        $this->assertNotNull($updatedUser);
        $this->assertNotSame($oldPasshash, $updatedUser->passhash);
        $this->assertSame('', (string) $updatedUser->editsecret);
        $this->assertSame('argon2id', $updatedUser->passhash_algo);
        $this->assertSame($oldAuthVersion + 1, (int) $updatedUser->auth_version);
        $this->assertTrue(PasswordHasher::verify(
            'ChosenPass123',
            (string) $updatedUser->passhash,
            (string) $updatedUser->secret,
            (string) $updatedUser->passhash_algo,
        ));
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
        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');

        // Token should be consumed (marked with consumed_at)
        $digest = $tokenService->digest($token);
        $consumedRow = DB::table('password_recovery_tokens')->where('token_digest', $digest)->first();
        $this->assertNotNull($consumedRow);
        $this->assertNotNull($consumedRow->consumed_at, 'Token should be marked as consumed');
    }

    public function test_reset_password_revokes_other_active_tokens_and_records_outbox(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $otherToken = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);
        $tokenService->store('password_recovery_tokens', $otherToken, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);
        $outboxBefore = DB::table('outbox_events')
            ->where('aggregate_type', 'user')
            ->where('aggregate_id', $userId)
            ->where('event_type', 'password.reset')
            ->count();

        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');

        $otherRow = DB::table('password_recovery_tokens')
            ->where('token_digest', $tokenService->digest($otherToken))
            ->first();
        $this->assertNotNull($otherRow);
        $this->assertSame(1, (int) $otherRow->revoked);
        $this->assertSame($outboxBefore + 1, DB::table('outbox_events')
            ->where('aggregate_type', 'user')
            ->where('aggregate_id', $userId)
            ->where('event_type', 'password.reset')
            ->count());
    }

    public function test_reset_password_leaves_token_active_when_password_confirmation_differs(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        try {
            $this->service->resetPassword($userId, $token, 'NewPass123', 'DifferentPass123');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            $row = DB::table('password_recovery_tokens')
                ->where('token_digest', $tokenService->digest($token))
                ->first();
            $this->assertNotNull($row);
            $this->assertNull($row->consumed_at);
            $this->assertSame(0, (int) $row->revoked);
            $this->assertNotNull($this->service->validateResetToken($userId, $token));
        }
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
        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');

        // Second reset with same token should fail
        $this->expectException(AuthenticationException::class);

        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');
    }

    public function test_request_reset_queues_mail_with_composed_subject(): void
    {
        Queue::fake();
        $this->createUser(['email' => 'subj@test.com']);

        $this->service->requestReset(['email' => 'subj@test.com'], '127.0.0.1', [], []);

        $siteName = (string) SiteConfig::current()->basic->siteName();
        Queue::assertPushed(SendLegacyMail::class, static function (SendLegacyMail $job) use ($siteName): bool {
            return $job->subject === $siteName.(string) (__('recover.mail_title'));
        });
    }

    public function test_reset_password_queues_password_changed_mail_with_composed_subject(): void
    {
        Queue::fake();
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $this->service->resetPassword($userId, $token, 'NewPass123', 'NewPass123');

        $siteName = (string) SiteConfig::current()->basic->siteName();
        Queue::assertPushed(SendLegacyMail::class, static function (SendLegacyMail $job) use ($siteName): bool {
            return $job->subject === $siteName.(string) (__('recover.mail_password_changed_title'));
        });
    }

    public function test_password_setup_validate_uses_composed_lang_key(): void
    {
        try {
            app(PasswordSetup::class)->validate('first-pass', 'other-pass', 'user', 'takesignup');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(
                (string) (__('takesignup.std_passwords_unmatched')),
                $e->getMessage(),
            );
        }
    }
}
