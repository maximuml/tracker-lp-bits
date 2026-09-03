<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NexusException;
use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for AuthRepository.
 *
 * Covers getLoginAttemptsSum(), banLoginAttempts(), recordFailedLogin(),
 * updateUserLang(), countUsers(), countUsersByIp(), getUserIdByUsername(),
 * isIpBanned(), updateUserPasskey(), updateLogin(), getPasskeyByUserId(),
 * findUserArrayForCookie(), and findUserModelForCookie().
 */
final class AuthRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AuthRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('loginattempts')->delete();
        DB::table('bans')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new AuthRepository;
    }

    public function test_get_login_attempts_sum_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getLoginAttemptsSum('1.2.3.4'));
    }

    public function test_get_login_attempts_sum_sums_attempts_for_ip(): void
    {
        DB::table('loginattempts')->insert([
            ['ip' => '1.2.3.4', 'added' => now()->toDateTimeString(), 'attempts' => 3, 'banned' => 0, 'type' => 'login'],
            ['ip' => '1.2.3.4', 'added' => now()->toDateTimeString(), 'attempts' => 2, 'banned' => 0, 'type' => 'login'],
            ['ip' => '5.6.7.8', 'added' => now()->toDateTimeString(), 'attempts' => 10, 'banned' => 0, 'type' => 'login'],
        ]);

        $this->assertSame(5, $this->repository->getLoginAttemptsSum('1.2.3.4'));
    }

    public function test_ban_login_attempts_updates_rows(): void
    {
        DB::table('loginattempts')->insert([
            ['ip' => '1.2.3.4', 'added' => now()->toDateTimeString(), 'attempts' => 3, 'banned' => 0, 'type' => 'login'],
            ['ip' => '5.6.7.8', 'added' => now()->toDateTimeString(), 'attempts' => 1, 'banned' => 0, 'type' => 'login'],
        ]);

        $this->repository->banLoginAttempts('1.2.3.4');

        $banned = DB::table('loginattempts')->where('ip', '1.2.3.4')->value('banned');
        $other = DB::table('loginattempts')->where('ip', '5.6.7.8')->value('banned');

        $this->assertTrue((bool) $banned);
        $this->assertFalse((bool) $other);
    }

    public function test_record_failed_login_inserts_new_row(): void
    {
        $this->repository->recordFailedLogin('1.2.3.4', false);

        $row = DB::table('loginattempts')->where('ip', '1.2.3.4')->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame('login', $row->type);
    }

    public function test_record_failed_login_increments_existing_row(): void
    {
        DB::table('loginattempts')->insert([
            'ip' => '1.2.3.4', 'added' => now()->toDateTimeString(), 'attempts' => 2, 'banned' => 0, 'type' => 'login',
        ]);

        $this->repository->recordFailedLogin('1.2.3.4', false);

        $this->assertSame(3, (int) DB::table('loginattempts')->where('ip', '1.2.3.4')->value('attempts'));
    }

    public function test_record_failed_login_sets_recover_type(): void
    {
        $this->repository->recordFailedLogin('1.2.3.4', true);

        $this->assertSame('recover', DB::table('loginattempts')->where('ip', '1.2.3.4')->value('type'));
    }

    public function test_update_user_lang_updates_lang(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['lang' => 6]);

        $this->repository->updateUserLang($user->id, 3);

        $this->assertSame(3, (int) DB::table('users')->where('id', $user->id)->value('lang'));
    }

    public function test_count_users_returns_total_count(): void
    {
        User::factory()->create();
        User::factory()->create();

        $this->assertSame(2, $this->repository->countUsers());
    }

    public function test_count_users_by_ip_returns_count_for_ip(): void
    {
        User::factory()->create(['ip' => '1.2.3.4']);
        User::factory()->create(['ip' => '1.2.3.4']);
        User::factory()->create(['ip' => '5.6.7.8']);

        $this->assertSame(2, $this->repository->countUsersByIp('1.2.3.4'));
    }

    public function test_get_user_id_by_username_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUserIdByUsername('nonexistent_user'));
    }

    public function test_get_user_id_by_username_returns_id_when_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame($user->id, $this->repository->getUserIdByUsername((string) $user->username));
    }

    public function test_get_user_id_by_username_is_case_insensitive(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['username' => 'TestUser']);

        $this->assertSame($user->id, $this->repository->getUserIdByUsername('testuser'));
    }

    public function test_is_ip_banned_returns_false_when_no_ban(): void
    {
        $this->assertFalse($this->repository->isIpBanned(1000));
    }

    public function test_is_ip_banned_returns_true_when_in_range(): void
    {
        DB::table('bans')->insert([
            'added' => now()->toDateTimeString(),
            'addedby' => 1,
            'comment' => 'test ban',
            'first' => 100,
            'last' => 200,
        ]);

        $this->assertTrue($this->repository->isIpBanned(150));
        $this->assertFalse($this->repository->isIpBanned(50));
        $this->assertFalse($this->repository->isIpBanned(250));
    }

    public function test_update_user_passkey_updates_passkey(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->updateUserPasskey($user->id, 'newpasskey123');

        $this->assertSame('newpasskey123', DB::table('users')->where('id', $user->id)->value('passkey'));
    }

    public function test_update_login_updates_user_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->updateLogin($user->id, ['last_access' => '2025-01-01 00:00:00']);

        $this->assertSame('2025-01-01 00:00:00', DB::table('users')->where('id', $user->id)->value('last_access'));
    }

    public function test_get_passkey_by_user_id_returns_passkey(): void
    {
        /** @var User $user */
        $user = User::factory()->withPasskey('mypasskey')->create();

        $this->assertSame('mypasskey', $this->repository->getPasskeyByUserId($user->id));
    }

    public function test_get_passkey_by_user_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getPasskeyByUserId(99999));
    }

    public function test_find_user_array_for_cookie_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findUserArrayForCookie(99999, false));
    }

    public function test_find_user_array_for_cookie_returns_data_when_confirmed_and_enabled(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'confirmed', 'enabled' => 1]);

        $result = $this->repository->findUserArrayForCookie($user->id, false);

        $this->assertIsArray($result);
        $this->assertSame($user->id, (int) $result['id']);
    }

    public function test_find_user_array_for_cookie_returns_null_when_not_confirmed(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'pending', 'enabled' => 1]);

        $this->assertNull($this->repository->findUserArrayForCookie($user->id, false));
    }

    public function test_find_user_array_for_cookie_returns_null_when_disabled_and_not_ignored(): void
    {
        /** @var User $user */
        $user = User::factory()->disabled()->create(['status' => 'confirmed']);

        $this->assertNull($this->repository->findUserArrayForCookie($user->id, false));
    }

    public function test_find_user_array_for_cookie_returns_data_when_disabled_but_ignored(): void
    {
        /** @var User $user */
        $user = User::factory()->disabled()->create(['status' => 'confirmed']);

        $result = $this->repository->findUserArrayForCookie($user->id, true);

        $this->assertIsArray($result);
        $this->assertSame($user->id, (int) $result['id']);
    }

    public function test_find_user_model_for_cookie_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findUserModelForCookie(99999, false));
    }

    public function test_find_user_model_for_cookie_returns_user_when_normal(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'confirmed', 'enabled' => 1]);

        $result = $this->repository->findUserModelForCookie($user->id, false);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_find_user_model_for_cookie_throws_when_disabled_and_not_ignored(): void
    {
        /** @var User $user */
        $user = User::factory()->disabled()->create(['status' => 'confirmed']);

        $this->expectException(NexusException::class);

        $this->repository->findUserModelForCookie($user->id, false);
    }

    public function test_find_user_model_for_cookie_returns_user_when_disabled_but_ignored(): void
    {
        /** @var User $user */
        $user = User::factory()->disabled()->create(['status' => 'confirmed']);

        $result = $this->repository->findUserModelForCookie($user->id, true);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }
}
