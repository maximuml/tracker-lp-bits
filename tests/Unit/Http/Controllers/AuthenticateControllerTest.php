<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AuthenticateController;
use App\Http\Requests\Auth\ChallengeRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->with('testuser', 'password', '')
            ->andReturn($loginResult);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = LoginRequest::create('/api/v1/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

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
        $request = LoginRequest::create('/api/v1/login', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

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

    public function test_challenge_returns_challenge_data(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', ['username' => 'testuser']);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->challenge($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('challenge', $result['data']);
        $this->assertArrayHasKey('secret', $result['data']);
        $this->assertArrayHasKey('passhash_algo', $result['data']);
    }

    public function test_challenge_validates_username_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->challenge($request);
    }
}
