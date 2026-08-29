<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ToolController;
use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class ToolControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_notifications_returns_success(): void
    {
        $notifications = [
            'unread_message_count' => 5,
            'unread_notification_count' => 3,
        ];

        /** @var ToolRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ToolRepository::class);
        $repository->shouldReceive('getNotificationCount')
            ->once()
            ->andReturn($notifications);

        $user = new User;
        $user->id = 5;
        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new ToolController($repository);

        $result = $controller->notifications();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_notifications_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var ToolRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ToolRepository::class);
        $repository->shouldNotReceive('getNotificationCount');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new ToolController($repository);

        $controller->notifications();
    }
}
