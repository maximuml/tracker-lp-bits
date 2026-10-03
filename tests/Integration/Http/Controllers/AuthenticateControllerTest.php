<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\AuthenticateController;
use App\Http\Requests\Auth\ChallengeRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
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
            ->with('testuser', 'password', '', Mockery::any())
            ->andReturn($loginResult);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
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

    public function test_login_adds_site_info_only_when_included(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('login')
            ->twice()
            ->andReturn(['token' => 'abc123']);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        $controller = new AuthenticateController($repository, $userRepository, $this->app);

        $withInclude = LoginRequest::create('/api/v1/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
            'include' => 'site_info',
        ]);
        $withInclude->setContainer(app());
        $withInclude->setRedirector(app('redirect'));
        $withInclude->validateResolved();
        $included = $controller->login($withInclude);
        $this->assertArrayHasKey('site_info', $included['data']);
        $this->assertArrayHasKey('site_name', $included['data']['site_info']);

        $withoutInclude = LoginRequest::create('/api/v1/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
            'include' => 'other',
        ]);
        $withoutInclude->setContainer(app());
        $withoutInclude->setRedirector(app('redirect'));
        $withoutInclude->validateResolved();
        $excluded = $controller->login($withoutInclude);
        $this->assertArrayNotHasKey('site_info', $excluded['data']);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldNotReceive('login');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
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
        $repository->shouldNotReceive('logout');

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $token = Mockery::mock(PersonalAccessToken::class);
        $token->shouldReceive('delete')->once();

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('currentAccessToken')->once()->andReturn($token);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = Request::create('/api/v1/logout', 'POST', []);
        $request->setUserResolver(static fn (): User => $user);

        $result = $controller->logout($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_logout_all_revokes_every_token(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);
        $repository->shouldReceive('logout')
            ->once()
            ->with(5)
            ->andReturn(2);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        Auth::shouldReceive('id')->once()->andReturn(5);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = Request::create('/api/v1/logout-all', 'POST', []);

        $result = $controller->logoutAll($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_challenge_returns_challenge_data(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('findByUsername')
            ->once()
            ->with('testuser', ['secret', 'passhash_algo'])
            ->andReturn(null);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
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

    public function test_challenge_is_20_byte_hex_cached_for_300_seconds(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('findByUsername')
            ->once()
            ->with('testuser', ['secret', 'passhash_algo'])
            ->andReturn(null);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', ['username' => 'testuser']);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        Cache::spy();
        $result = $controller->challenge($request);

        $challenge = $result['data']['challenge'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $challenge);
        Cache::shouldHaveReceived('put')
            ->once()
            ->with('challenge:testuser', $challenge, 300);
    }

    public function test_challenge_returns_existing_user_secret_and_algo(): void
    {
        $user = User::factory()->create([
            'secret' => str_repeat('ab', 16),
            'passhash_algo' => 'md5',
        ]);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('findByUsername')
            ->once()
            ->with($user->username, ['secret', 'passhash_algo'])
            ->andReturn($user);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', ['username' => $user->username]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->challenge($request);

        $this->assertSame($user->secret, $result['data']['secret']);
        $this->assertSame('md5', $result['data']['passhash_algo']);
    }

    public function test_challenge_unknown_user_gets_fallback_secret_and_sha256(): void
    {
        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('findByUsername')
            ->once()
            ->with('no_such_user_xyz', ['secret', 'passhash_algo'])
            ->andReturn(null);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', ['username' => 'no_such_user_xyz']);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->challenge($request);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result['data']['secret']);
        $this->assertSame('sha256', $result['data']['passhash_algo']);
    }

    public function test_challenge_validates_username_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AuthenticateRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AuthenticateRepository::class);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new AuthenticateController($repository, $userRepository, $this->app);
        $request = ChallengeRequest::create('/api/v1/challenge', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->challenge($request);
    }
}
