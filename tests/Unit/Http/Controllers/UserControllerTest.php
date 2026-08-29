<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UserController;
use App\Http\Requests\UidRequest;
use App\Http\Requests\UserDisableRequest;
use App\Http\Requests\UserIncrementDecrementRequest;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

final class UserControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_paginated_user_list(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15, 1);

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        $controller = new UserController($repository, $examRepository);
        $request = Request::create('/api/v1/users', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_classes_returns_user_class_list(): void
    {
        $classes = [
            ['value' => 1, 'text' => 'User'],
            ['value' => 2, 'text' => 'Power User'],
        ];

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('listClass')
            ->once()
            ->andReturn($classes);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        $controller = new UserController($repository, $examRepository);

        $result = $controller->classes();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_base_returns_current_user_base_info(): void
    {
        $user = new User;
        $user->id = 5;
        $user->username = 'testuser';
        $user->email = 'test@example.com';
        $user->avatar = 'default.png';
        $user->status = 'confirmed';
        $user->enabled = 'yes';
        $user->added = now()->toDateTimeString();
        $user->last_access = now()->toDateTimeString();
        $user->last_login = now()->toDateTimeString();
        $user->class = 1;

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('getBase')
            ->once()
            ->with(5)
            ->andReturn($user);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('id')->once()->andReturn(5);
        Gate::shouldReceive('allows')->andReturn(false);

        $controller = new UserController($repository, $examRepository);

        $result = $controller->base();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_show_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldNotReceive('getDetail');

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);

        $controller->show(null);
    }

    public function test_disable_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldNotReceive('disableUser');

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);
        $request = UserDisableRequest::create('/api/v1/users/disable', 'POST', ['uid' => 10, 'reason' => 'Test']);

        $controller->disable($request);
    }

    public function test_enable_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldNotReceive('enableUser');

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);
        $request = UidRequest::create('/api/v1/users/enable', 'POST', ['uid' => 10]);

        $controller->enable($request);
    }

    public function test_me_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);
        $request = Request::create('/api/v1/users/me', 'GET', []);

        $controller->me();
    }

    public function test_publish_torrent_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);
        $request = Request::create('/api/v1/users/publish-torrent', 'GET', []);

        $controller->publishTorrent($request);
    }

    public function test_mod_comment_returns_comment_string(): void
    {
        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('getModComment')
            ->once()
            ->with(10)
            ->andReturn('Test mod comment');

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        $controller = new UserController($repository, $examRepository);
        $request = UidRequest::create('/api/v1/users/mod-comment', 'POST', ['uid' => 10]);

        $result = $controller->modComment($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_invite_info_returns_null_when_no_invite(): void
    {
        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('getInviteInfo')
            ->once()
            ->with(10)
            ->andReturn(null);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        $controller = new UserController($repository, $examRepository);
        $request = UidRequest::create('/api/v1/users/invite-info', 'POST', ['uid' => 10]);

        $result = $controller->inviteInfo($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_increment_decrement_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldNotReceive('incrementDecrement');

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new UserController($repository, $examRepository);
        $request = UserIncrementDecrementRequest::create('/api/v1/users/increment-decrement', 'POST', [
            'uid' => 10,
            'action' => 'increment',
            'field' => 'seedbonus',
            'value' => 100,
            'reason' => 'Test',
        ]);

        $controller->incrementDecrement($request);
    }

    public function test_remove_two_step_authentication_calls_repository(): void
    {
        /** @var UserRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepository::class);
        $repository->shouldReceive('removeTwoStepAuthentication')
            ->once()
            ->with(Mockery::any(), 10)
            ->andReturn(true);

        /** @var ExamRepository&Mockery\MockInterface $examRepository */
        $examRepository = Mockery::mock(ExamRepository::class);

        $user = new User;
        $user->id = 5;
        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new UserController($repository, $examRepository);
        $request = UidRequest::create('/api/v1/users/remove-two-step', 'POST', ['uid' => 10]);

        $result = $controller->removeTwoStepAuthentication($request);

        $this->assertSame(0, $result['ret']);
    }
}
