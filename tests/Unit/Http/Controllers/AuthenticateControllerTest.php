<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AuthenticateController;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class AuthenticateControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_returns_success_with_valid_credentials(): void
    {
        $loginResult = ['token' => 'abc123', 'user' => ['id' => 5]];

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('login')
            ->once()
            ->with('testuser', 'password')
            ->andReturn($loginResult);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $result = $controller->login($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldNotReceive('login');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/login', 'POST', []);

        $controller->login($request);
    }

    public function test_logout_returns_success(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('logout')
            ->once()
            ->with(5)
            ->andReturn(true);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        Auth::shouldReceive('id')->once()->andReturn(5);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/logout', 'POST', []);

        $result = $controller->logout($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_nas_tools_approve_returns_success(): void
    {
        $user = new User;
        $user->id = 5;
        $user->username = 'testuser';
        $user->email = 'test@example.com';
        $user->class = 1;

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('nasToolsApprove')
            ->once()
            ->with('encrypted-data')
            ->andReturn($user);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/nas-tools/approve', 'POST', ['data' => 'encrypted-data']);

        $result = $controller->nasToolsApprove($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_nas_tools_approve_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldNotReceive('nasToolsApprove');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/nas-tools/approve', 'POST', []);

        $controller->nasToolsApprove($request);
    }

    public function test_nas_tools_approve_returns_fail_on_exception(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('nasToolsApprove')
            ->once()
            ->andThrow(new \InvalidArgumentException('Invalid data format.'));

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/nas-tools/approve', 'POST', ['data' => 'bad-data']);

        $result = $controller->nasToolsApprove($request);

        $this->assertNotSame(0, $result['ret']);
    }

    public function test_iyuu_approve_returns_success(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('iyuuApprove')
            ->once()
            ->with('token123', 5, 'verity123');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/iyuu/approve', 'POST', [
            'token' => 'token123',
            'id' => 5,
            'verity' => 'verity123',
            'provider' => 'iyuu',
        ]);

        $response = $controller->iyuuApprove($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_iyuu_approve_validates_required_fields(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldNotReceive('iyuuApprove');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/iyuu/approve', 'POST', []);

        $response = $controller->iyuuApprove($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_challenge_returns_challenge_data(): void
    {
        Cache::shouldReceive('put')->once();

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/challenge', 'POST', ['username' => 'testuser']);

        $result = $controller->challenge($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('challenge', $result['data']);
        $this->assertArrayHasKey('secret', $result['data']);
        $this->assertArrayHasKey('passhash_algo', $result['data']);
    }

    public function test_challenge_validates_username_required(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = Request::create('/api/v1/challenge', 'POST', []);

        $result = $controller->challenge($request);

        $this->assertNotSame(0, $result['ret']);
    }
}
