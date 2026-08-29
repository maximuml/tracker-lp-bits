<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class AttendanceControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_attend_returns_success_for_authenticated_user(): void
    {
        $attendance = new Attendance([
            'uid' => 5,
            'points' => 100,
            'days' => 1,
            'total_days' => 1,
        ]);
        $attendance->is_updated = 1;

        /** @var AttendanceRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AttendanceRepository::class);
        $repository->shouldReceive('attend')
            ->once()
            ->with(5)
            ->andReturn($attendance);

        $this->authenticateUser(5);

        $controller = app(AttendanceController::class);
        $request = Request::create('/api/v1/attendance', 'POST', []);

        $result = $controller->attend($request, $repository);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(5, $result['data']['uid']);
        $this->assertSame(100, $result['data']['points']);
        $this->assertSame(1, $result['data']['days']);
        $this->assertSame(1, $result['data']['total_days']);
        $this->assertSame(1, $result['data']['is_updated']);
    }

    public function test_attend_returns_fail_when_not_authenticated(): void
    {
        /** @var AttendanceRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AttendanceRepository::class);
        $repository->shouldNotReceive('attend');

        Auth::shouldReceive('user')->andReturn(null);
        app()->forgetInstance(CurrentUser::class);

        $controller = app(AttendanceController::class);
        $request = Request::create('/api/v1/attendance', 'POST', []);

        $result = $controller->attend($request, $repository);

        $this->assertSame(-1, $result['ret']);
    }

    public function test_attend_returns_already_attended_when_not_updated(): void
    {
        $attendance = new Attendance([
            'uid' => 5,
            'points' => 100,
            'days' => 3,
            'total_days' => 3,
        ]);
        $attendance->is_updated = 0;

        /** @var AttendanceRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AttendanceRepository::class);
        $repository->shouldReceive('attend')
            ->once()
            ->with(5)
            ->andReturn($attendance);

        $this->authenticateUser(5);

        $controller = app(AttendanceController::class);
        $request = Request::create('/api/v1/attendance', 'POST', []);

        $result = $controller->attend($request, $repository);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(0, $result['data']['is_updated']);
    }

    /**
     * Authenticate a user with the given ID via Auth facade and refresh CurrentUser.
     */
    private function authenticateUser(int $userId): void
    {
        $user = new User;
        $user->id = $userId;
        $user->class = 1;

        Auth::shouldReceive('user')->andReturn($user);
        app()->forgetInstance(CurrentUser::class);
    }
}
