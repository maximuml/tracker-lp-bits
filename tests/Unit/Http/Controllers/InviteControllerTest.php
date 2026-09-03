<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\InviteController;
use App\Repositories\UserRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class InviteControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalLang();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_invite_denies_access_for_guest_without_id(): void
    {
        $this->mockCurrentUser(null);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        app()->instance(UserRepository::class, $userRepository);

        $controller = app(InviteController::class);
        $request = Request::create('/invite', 'GET');
        app()->instance('request', $request);

        $response = $controller->invite($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_invite_denies_access_when_guest_views_other_user(): void
    {
        $this->mockCurrentUser(null);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        app()->instance(UserRepository::class, $userRepository);

        $controller = app(InviteController::class);
        $request = Request::create('/invite', 'GET', ['id' => 999]);
        app()->instance('request', $request);

        $response = $controller->invite($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_invite_denies_new_type_for_guest(): void
    {
        $this->mockCurrentUser(null);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        app()->instance(UserRepository::class, $userRepository);

        $controller = app(InviteController::class);
        $request = Request::create('/invite', 'GET', ['type' => 'new']);
        app()->instance('request', $request);

        $response = $controller->invite($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }

    /**
     * Set up minimal language strings so legacyAbortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void
    {
        app(Globals::class)->set('lang_functions', [
            'text_login' => 'Login',
            'text_signup' => 'Signup',
        ]);
    }
}
