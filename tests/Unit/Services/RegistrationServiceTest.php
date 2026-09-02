<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\AuthenticationException;
use App\Repositories\UserRepository;
use App\Services\RegistrationService;
use App\Services\WebAuthService;
use App\Support\Settings;
use App\Support\Strings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Unit tests for RegistrationService.
 *
 * Covers assertCanRegister (IP ban, registration toggles, max users,
 * max accounts per IP) and confirm (account confirmation flow).
 */
final class RegistrationServiceTest extends TestCase
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
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::table('loginattempts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        // Reset Settings static cache so SiteConfig reads fresh DB values
        $this->resetSettingsCache();
    }

    private function resetSettingsCache(): void
    {
        $reflection = new ReflectionClass(Settings::class);
        $settings = $reflection->getProperty('settings');
        $settings->setValue(null, null);
        $fromDb = $reflection->getProperty('fromDb');
        $fromDb->setValue(null, null);
    }

    private function service(): RegistrationService
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        return new RegistrationService($authService, $userRepository);
    }

    private function serviceWithAuth(WebAuthService $authService): RegistrationService
    {
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        return new RegistrationService($authService, $userRepository);
    }

    /** @return array<string, string> */
    private function emptyLang(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertUser(array $overrides = []): int
    {
        $defaults = [
            'username' => 'testuser',
            'email' => 'test@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => 'passkey',
            'class' => UserClassEnum::USER->value,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
        ];

        return DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    // --- assertCanRegister ---

    public function test_assert_can_register_rethrows_banned_ip(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService
            ->shouldReceive('assertNotBanned')
            ->with('1.2.3.4')
            ->once()
            ->andThrow(new AuthenticationException('banned'));

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)
            ->assertCanRegister('normal', '1.2.3.4', $this->emptyLang(), $this->emptyLang());
    }

    public function test_assert_can_register_passes_for_normal_when_registration_open(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService
            ->shouldReceive('assertNotBanned')
            ->with('10.0.0.1')
            ->once();

        $this->serviceWithAuth($authService)
            ->assertCanRegister('normal', '10.0.0.1', $this->emptyLang(), $this->emptyLang());
        $this->expectNotToPerformAssertions();
    }

    public function test_assert_can_register_passes_for_invite_when_invite_system_enabled(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'main.invitesystem'],
            ['value' => 'yes'],
        );
        $this->resetSettingsCache();

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService
            ->shouldReceive('assertNotBanned')
            ->with('10.0.0.2')
            ->once();

        $this->serviceWithAuth($authService)
            ->assertCanRegister('invite', '10.0.0.2', $this->emptyLang(), $this->emptyLang());
        $this->expectNotToPerformAssertions();
    }

    public function test_assert_can_register_rejects_when_max_users_reached(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'main.maxusers'],
            ['value' => '1'],
        );
        $this->resetSettingsCache();

        $this->insertUser(['username' => 'existinguser', 'email' => 'existing@test.com']);

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService
            ->shouldReceive('assertNotBanned')
            ->with('10.0.0.3')
            ->once();

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)
            ->assertCanRegister('normal', '10.0.0.3', $this->emptyLang(), $this->emptyLang());
    }

    public function test_assert_can_register_rejects_when_max_ip_accounts_exceeded(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'security.maxip'],
            ['value' => '1'],
        );
        $this->resetSettingsCache();

        $this->insertUser(['username' => 'user0', 'email' => 'user0@test.com', 'ip' => '10.0.0.99']);
        $this->insertUser(['username' => 'user1', 'email' => 'user1@test.com', 'ip' => '10.0.0.99']);

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService
            ->shouldReceive('assertNotBanned')
            ->with('10.0.0.99')
            ->once();

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)
            ->assertCanRegister('normal', '10.0.0.99', $this->emptyLang(), $this->emptyLang());
    }

    // --- confirm ---

    public function test_confirm_throws_404_for_nonexistent_user(): void
    {
        $this->expectException(HttpException::class);

        $this->service()->confirm(999999, 'wrongmd5', '1.2.3.4');
    }

    public function test_confirm_returns_user_if_already_confirmed(): void
    {
        $secret = 'testsecret123';
        $id = $this->insertUser([
            'username' => 'confirmeduser',
            'email' => 'confirmed@test.com',
            'secret' => $secret,
            'passkey' => 'passkey123',
            'status' => 'confirmed',
            'editsecret' => '',
        ]);

        $user = $this->service()->confirm($id, 'wrongmd5', '1.2.3.4');

        $this->assertSame('confirmed', $user->status);
    }

    public function test_confirm_throws_404_for_pending_user_with_wrong_md5(): void
    {
        $secret = 'testsecret456';
        $id = $this->insertUser([
            'username' => 'pendinguser',
            'email' => 'pending@test.com',
            'secret' => $secret,
            'passkey' => 'passkey456',
            'status' => 'pending',
            'editsecret' => $secret,
        ]);

        $this->expectException(HttpException::class);

        $this->service()->confirm($id, 'wrongmd5hash', '1.2.3.4');
    }

    public function test_confirm_confirms_pending_user_with_correct_md5(): void
    {
        $secret = 'testsecret789';
        $id = $this->insertUser([
            'username' => 'pendinguser2',
            'email' => 'pending2@test.com',
            'secret' => $secret,
            'passkey' => 'passkey789',
            'status' => 'pending',
            'editsecret' => $secret,
        ]);

        $confirmMd5 = md5(Strings::padHash($secret));

        $user = $this->service()->confirm($id, $confirmMd5, '1.2.3.4');

        $this->assertSame('confirmed', $user->status);
        $this->assertSame('', $user->editsecret);
    }

    // --- resendConfirmation ---

    public function test_resend_confirmation_throws_when_admin_verification_required(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'main.verification'],
            ['value' => 'admin'],
        );
        $this->resetSettingsCache();

        $this->expectException(AuthenticationException::class);

        $this->service()->resendConfirmation(
            ['email' => 'test@test.com', 'wantpassword' => 'pass', 'passagain' => 'pass'],
            '1.2.3.4',
            'en',
            $this->emptyLang(),
            $this->emptyLang(),
        );
    }

    public function test_resend_confirmation_throws_on_blank_fields(): void
    {
        $this->setEmailVerification();
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->with('1.2.3.4')->once();
        $authService->shouldReceive('isCaptchaEnabled')->once()->andReturn(false);

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)->resendConfirmation(
            ['email' => '', 'wantpassword' => '', 'passagain' => ''],
            '1.2.3.4',
            'en',
            $this->emptyLang(),
            $this->emptyLang(),
        );
    }

    public function test_resend_confirmation_throws_on_invalid_email(): void
    {
        $this->setEmailVerification();
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->with('1.2.3.4')->once();
        $authService->shouldReceive('isCaptchaEnabled')->once()->andReturn(false);
        $authService->shouldReceive('recordFailedAttempt')->with('1.2.3.4')->once();

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)->resendConfirmation(
            ['email' => 'not-an-email', 'wantpassword' => 'password', 'passagain' => 'password'],
            '1.2.3.4',
            'en',
            $this->emptyLang(),
            $this->emptyLang(),
        );
    }

    public function test_resend_confirmation_throws_when_email_not_found(): void
    {
        $this->setEmailVerification();
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->with('1.2.3.4')->once();
        $authService->shouldReceive('isCaptchaEnabled')->once()->andReturn(false);
        $authService->shouldReceive('recordFailedAttempt')->with('1.2.3.4')->once();

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)->resendConfirmation(
            ['email' => 'nonexistent@test.com', 'wantpassword' => 'password', 'passagain' => 'password'],
            '1.2.3.4',
            'en',
            $this->emptyLang(),
            $this->emptyLang(),
        );
    }

    public function test_resend_confirmation_throws_when_user_already_confirmed(): void
    {
        $this->setEmailVerification();
        $this->insertUser([
            'username' => 'confirmeduser2',
            'email' => 'already@confirmed.com',
        ]);

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->with('1.2.3.4')->once();
        $authService->shouldReceive('isCaptchaEnabled')->once()->andReturn(false);
        $authService->shouldReceive('recordFailedAttempt')->with('1.2.3.4')->once();

        $this->expectException(AuthenticationException::class);

        $this->serviceWithAuth($authService)->resendConfirmation(
            ['email' => 'already@confirmed.com', 'wantpassword' => 'password', 'passagain' => 'password'],
            '1.2.3.4',
            'en',
            $this->emptyLang(),
            $this->emptyLang(),
        );
    }

    private function setEmailVerification(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'main.verification'],
            ['value' => 'email'],
        );
        $this->resetSettingsCache();
    }
}
