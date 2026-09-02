<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\WebAuthService;
use App\Support\PasswordHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for WebAuthService.
 *
 * Covers password validation (argon2id/sha256/md5), login attempt
 * tracking, IP banning, and remaining attempt calculation.
 */
final class WebAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('loginattempts')->truncate();
    }

    private function service(): WebAuthService
    {
        /** @var UserRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(UserRepository::class);

        return new WebAuthService($repo);
    }

    // --- validatePassword ---

    public function test_validate_password_rejects_empty_password(): void
    {
        $user = new User;
        $user->passhash = PasswordHasher::hash('secret');
        $user->passhash_algo = PasswordHasher::ALGO_ARGON2ID;
        $user->secret = 'somesecret';
        $user->auth_key = 'somekey';

        $this->assertFalse($this->service()->validatePassword($user, ''));
    }

    public function test_validate_password_accepts_correct_argon2id(): void
    {
        $user = new User;
        $user->passhash = PasswordHasher::hash('myPassword123');
        $user->passhash_algo = PasswordHasher::ALGO_ARGON2ID;
        $user->secret = 'somesecret';
        $user->auth_key = 'somekey';

        $this->assertTrue($this->service()->validatePassword($user, 'myPassword123'));
    }

    public function test_validate_password_rejects_wrong_argon2id(): void
    {
        $user = new User;
        $user->passhash = PasswordHasher::hash('correctPassword');
        $user->passhash_algo = PasswordHasher::ALGO_ARGON2ID;
        $user->secret = 'somesecret';
        $user->auth_key = 'somekey';

        $this->assertFalse($this->service()->validatePassword($user, 'wrongPassword'));
    }

    public function test_validate_password_accepts_correct_sha256(): void
    {
        $password = 'legacyPass';
        $secret = 'mysecret';
        $user = new User;
        $user->id = 99999;
        $user->passhash = hash('sha256', $secret.hash('sha256', $password));
        $user->passhash_algo = PasswordHasher::ALGO_SHA256;
        $user->secret = $secret;
        $user->auth_key = 'somekey';

        // Use a mock service to avoid DB write during rehash
        /** @var UserRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(UserRepository::class);
        $service = new WebAuthService($repo);

        $this->assertTrue($service->validatePassword($user, $password));
    }

    public function test_validate_password_rejects_wrong_sha256(): void
    {
        $secret = 'mysecret';
        $user = new User;
        $user->passhash = hash('sha256', $secret.hash('sha256', 'correctPassword'));
        $user->passhash_algo = PasswordHasher::ALGO_SHA256;
        $user->secret = $secret;
        $user->auth_key = 'somekey';

        $this->assertFalse($this->service()->validatePassword($user, 'wrongPassword'));
    }

    public function test_validate_password_accepts_md5_without_auth_key(): void
    {
        $password = 'ancientPass';
        $secret = 'md5secret';
        $user = new User;
        $user->passhash = md5($secret.$password.$secret);
        $user->passhash_algo = PasswordHasher::ALGO_MD5;
        $user->secret = $secret;
        $user->auth_key = '';

        $this->assertTrue($this->service()->validatePassword($user, $password));
    }

    public function test_validate_password_accepts_md5_with_auth_key_via_main_path(): void
    {
        // md5 with auth_key set doesn't take the early-return path,
        // but the main verification path still uses the algo field ('md5')
        // and will verify successfully, then rehash to argon2id.
        $password = 'ancientPass';
        $secret = 'md5secret';
        $user = new User;
        $user->id = 99999;
        $user->passhash = md5($secret.$password.$secret);
        $user->passhash_algo = PasswordHasher::ALGO_MD5;
        $user->secret = $secret;
        $user->auth_key = 'haskey';

        $this->assertTrue($this->service()->validatePassword($user, $password));
    }

    public function test_validate_password_falls_back_to_sha256_when_argon2id_fails(): void
    {
        $password = 'fallbackPass';
        $secret = 'fbsecret';
        $user = new User;
        // Set algo to md5 but with auth_key set (so md5 is skipped)
        // The hash is sha256 format, so the sha256 fallback should match
        $user->passhash = hash('sha256', $secret.hash('sha256', $password));
        $user->passhash_algo = PasswordHasher::ALGO_MD5;
        $user->secret = $secret;
        $user->auth_key = 'haskey';

        $this->assertTrue($this->service()->validatePassword($user, $password));
    }

    // --- recordFailedAttempt ---

    public function test_record_failed_attempt_inserts_new_row(): void
    {
        $this->service()->recordFailedAttempt('192.168.1.1');

        $row = DB::table('loginattempts')->where('ip', '192.168.1.1')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_record_failed_attempt_increments_existing_row(): void
    {
        $this->service()->recordFailedAttempt('10.0.0.1');
        $this->service()->recordFailedAttempt('10.0.0.1');
        $this->service()->recordFailedAttempt('10.0.0.1');

        $row = DB::table('loginattempts')->where('ip', '10.0.0.1')->first();
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->attempts);
    }

    public function test_record_failed_attempt_tracks_different_ips_separately(): void
    {
        $this->service()->recordFailedAttempt('10.0.0.1');
        $this->service()->recordFailedAttempt('10.0.0.2');
        $this->service()->recordFailedAttempt('10.0.0.1');

        $this->assertSame(
            2,
            (int) DB::table('loginattempts')->where('ip', '10.0.0.1')->value('attempts')
        );
        $this->assertSame(
            1,
            (int) DB::table('loginattempts')->where('ip', '10.0.0.2')->value('attempts')
        );
    }

    // --- remainingAttempts ---

    public function test_remaining_attempts_returns_max_when_no_failures(): void
    {
        $remaining = $this->service()->remainingAttempts('172.16.0.1');

        $this->assertGreaterThan(0, $remaining);
    }

    public function test_remaining_attempts_decreases_with_failures(): void
    {
        $ip = '172.16.0.2';
        $max = $this->service()->maxLoginAttempts();

        $this->service()->recordFailedAttempt($ip);
        $this->service()->recordFailedAttempt($ip);

        $remaining = $this->service()->remainingAttempts($ip);
        $this->assertSame($max - 2, $remaining);
    }

    public function test_remaining_attempts_never_goes_negative(): void
    {
        $ip = '172.16.0.3';

        // Insert more attempts than the max
        for ($i = 0; $i < 20; $i++) {
            DB::table('loginattempts')->insert([
                'ip' => $ip,
                'added' => now()->toDateTimeString(),
                'attempts' => 1,
            ]);
        }

        $remaining = $this->service()->remainingAttempts($ip);
        $this->assertSame(0, $remaining);
    }

    // --- assertNotBanned ---

    public function test_assert_not_banned_passes_with_few_attempts(): void
    {
        $this->service()->recordFailedAttempt('192.168.99.1');

        // Should not throw
        $this->service()->assertNotBanned('192.168.99.1');
        $this->expectNotToPerformAssertions();
    }

    public function test_assert_not_banned_throws_when_too_many_attempts(): void
    {
        $ip = '192.168.99.2';
        $max = $this->service()->maxLoginAttempts();

        // Insert enough attempts to exceed the max
        DB::table('loginattempts')->insert([
            'ip' => $ip,
            'added' => now()->toDateTimeString(),
            'attempts' => $max + 5,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('banned');

        $this->service()->assertNotBanned($ip);
    }

    public function test_assert_not_banned_marks_ip_as_banned(): void
    {
        $ip = '192.168.99.3';
        $max = $this->service()->maxLoginAttempts();

        DB::table('loginattempts')->insert([
            'ip' => $ip,
            'added' => now()->toDateTimeString(),
            'attempts' => $max + 1,
        ]);

        try {
            $this->service()->assertNotBanned($ip);
            $this->fail('Expected AuthenticationException was not thrown');
        } catch (AuthenticationException) {
            // expected
        }

        $banned = DB::table('loginattempts')->where('ip', $ip)->value('banned');
        $this->assertTrue((bool) $banned, 'IP should be marked as banned in the database');
    }

    public function test_assert_not_banned_passes_for_clean_ip(): void
    {
        $this->service()->assertNotBanned('10.10.10.10');
        $this->expectNotToPerformAssertions();
    }

    // --- maxLoginAttempts ---

    public function test_max_login_attempts_returns_positive_integer(): void
    {
        $max = $this->service()->maxLoginAttempts();

        $this->assertGreaterThan(0, $max);
    }
}
