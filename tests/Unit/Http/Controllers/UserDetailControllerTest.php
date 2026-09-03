<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UserDetailController;
use App\Repositories\HitAndRunRepository;
use App\Repositories\UserRepository;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class UserDetailControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_show_redirects_guest_to_userdetails_php(): void
    {
        $this->bindRepositories();
        $this->mockCurrentUser(null);

        $controller = app(UserDetailController::class);
        $request = Request::create('/user/details', 'GET', ['id' => 5]);
        app()->instance('request', $request);

        $response = $controller->show($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/userdetails.php', $response->getTargetUrl());
        $this->assertStringContainsString('id=5', $response->getTargetUrl());
    }

    public function test_show_aborts_when_no_id_and_guest(): void
    {
        $this->bindRepositories();
        $this->mockCurrentUser(null);

        $controller = app(UserDetailController::class);
        $request = Request::create('/user/details', 'GET');
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request);
    }

    public function test_show_aborts_when_id_is_zero_and_guest(): void
    {
        $this->bindRepositories();
        $this->mockCurrentUser(null);

        $controller = app(UserDetailController::class);
        $request = Request::create('/user/details', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request);
    }

    public function test_show_aborts_when_id_is_negative_and_guest(): void
    {
        $this->bindRepositories();
        $this->mockCurrentUser(null);

        $controller = app(UserDetailController::class);
        $request = Request::create('/user/details', 'GET', ['id' => -3]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request);
    }

    /**
     * Bind mock repositories so the controller can be resolved from the container.
     */
    private function bindRepositories(): void
    {
        /** @var HitAndRunRepository&Mockery\MockInterface $hitAndRunRepository */
        $hitAndRunRepository = Mockery::mock(HitAndRunRepository::class);
        app()->instance(HitAndRunRepository::class, $hitAndRunRepository);

        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);
        app()->instance(UserRepository::class, $userRepository);
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
}
