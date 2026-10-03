<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Http\Controllers\ToolController;
use App\Models\User;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class ToolControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_notifications_returns_repository_counts_for_user(): void
    {
        $user = tap(new User, fn (User $u) => $u->id = 42);
        $payload = ['unread_messages' => 3, 'staff_messages' => 0];

        /** @var ToolRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ToolRepositoryInterface::class);
        $repository->shouldReceive('getNotificationCount')
            ->once()
            ->with($user)
            ->andReturn($payload);
        $this->app->instance(ToolRepositoryInterface::class, $repository);

        $this->be($user);

        $result = $this->app->make(ToolController::class)->notifications();

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    public function test_notifications_throws_when_unauthenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        $this->app->make(ToolController::class)->notifications();
    }
}
