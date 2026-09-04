<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Services\WebAuthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for AuthenticateRepository.
 *
 * Covers login() and logout().
 */
final class AuthenticateRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AuthenticateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('personal_access_tokens')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new AuthenticateRepository;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_throws_when_user_not_found(): void
    {
        $this->mockWebAuthService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username or password invalid.');

        $this->repository->login('nonexistent_user', 'password');
    }

    public function test_login_throws_when_password_invalid(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->mockWebAuthService(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username or password invalid.');

        $this->repository->login($user->username, 'wrongpassword');
    }

    public function test_login_succeeds_and_returns_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $this->mockWebAuthService(true);

        $result = $this->repository->login($user->username, '123456');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame($user->id, (int) $result['id']);
    }

    public function test_login_throws_when_two_step_required_but_code_empty(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->mockWebAuthService(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Require two-step code.');

        $this->repository->login($user->username, '123456');
    }

    public function test_login_throws_when_two_step_code_invalid(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['two_step_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->mockWebAuthService(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid two-step code.');

        $this->repository->login($user->username, '123456', '000000');
    }

    public function test_logout_deletes_user_tokens(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->createToken('test-token');

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $result = $this->repository->logout($user->id);

        $this->assertSame(1, $result);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function test_logout_throws_when_user_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->logout(99999);
    }

    /**
     * Bind a mock WebAuthService into the container.
     * When $validateResult is null, the mock is not configured (use for
     * tests where validatePassword is never reached).
     */
    private function mockWebAuthService(?bool $validateResult = null): void
    {
        /** @var WebAuthService&MockInterface $mock */
        $mock = Mockery::mock(WebAuthService::class);

        if ($validateResult !== null) {
            $mock->shouldReceive('validatePassword')
                ->andReturn($validateResult);
        }

        $this->app->instance(WebAuthService::class, $mock);
    }
}
