<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\UserController;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserResetPasswordRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class UserControllerApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_delegates_to_repository_get_list(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15, 1);

        /** @var UserRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);
        $this->app->instance(UserRepositoryInterface::class, $repository);

        $request = UserIndexRequest::create('/api/users', 'GET', []);
        $request->setContainer($this->app);
        $request->validateResolved();

        $result = $this->app->make(UserController::class)->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_reset_password_delegates_to_repository(): void
    {
        $data = [
            'uid' => 9,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ];

        /** @var UserRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldReceive('resetPassword')
            ->once()
            ->with(9, 'new-secret-123', 'new-secret-123')
            ->andReturn(true);
        $this->app->instance(UserRepositoryInterface::class, $repository);

        $request = UserResetPasswordRequest::create('/api/users/reset-password', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $result = $this->app->make(UserController::class)->resetPassword($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('Reset password success!', $result['msg']);
    }
}
