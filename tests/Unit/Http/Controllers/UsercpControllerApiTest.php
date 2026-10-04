<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Http\Controllers\UsercpController;
use Illuminate\Http\Request;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class UsercpControllerApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_settings_get_returns_repository_settings(): void
    {
        $payload = ['avatar' => 'x.png'];

        /** @var UsercpRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UsercpRepositoryInterface::class);
        $repository->shouldReceive('settings')->once()->andReturn($payload);
        $this->app->instance(UsercpRepositoryInterface::class, $repository);

        $request = Request::create('/api/usercp/settings', 'GET');
        $result = $this->app->make(UsercpController::class)->settings($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    public function test_settings_post_delegates_to_update_personal(): void
    {
        $payload = ['saved' => true];

        /** @var UsercpRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UsercpRepositoryInterface::class);
        $repository->shouldReceive('updatePersonal')
            ->once()
            ->with(Mockery::type(PersonalSettingsDto::class))
            ->andReturn($payload);
        $this->app->instance(UsercpRepositoryInterface::class, $repository);

        $request = Request::create('/api/usercp/settings', 'POST', ['parked' => 'no']);
        $result = $this->app->make(UsercpController::class)->settingsPost($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    public function test_forum_delegates_to_update_forum(): void
    {
        $payload = ['saved' => true];

        /** @var UsercpRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UsercpRepositoryInterface::class);
        $repository->shouldReceive('updateForum')
            ->once()
            ->with(Mockery::type(ForumSettingsDto::class))
            ->andReturn($payload);
        $this->app->instance(UsercpRepositoryInterface::class, $repository);

        $request = Request::create('/api/usercp/forum', 'POST', ['topicsperpage' => 20]);
        $result = $this->app->make(UsercpController::class)->forum($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    public function test_tracker_delegates_to_update_tracker(): void
    {
        $payload = ['saved' => true];

        /** @var UsercpRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UsercpRepositoryInterface::class);
        $repository->shouldReceive('updateTracker')
            ->once()
            ->with(Mockery::type(TrackerSettingsDto::class))
            ->andReturn($payload);
        $this->app->instance(UsercpRepositoryInterface::class, $repository);

        $request = Request::create('/api/usercp/tracker', 'POST', ['pmnotif' => 'yes']);
        $result = $this->app->make(UsercpController::class)->tracker($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    public function test_security_delegates_to_update_security_api(): void
    {
        $payload = ['saved' => true];

        /** @var UsercpRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(UsercpRepositoryInterface::class);
        $repository->shouldReceive('updateSecurityApi')
            ->once()
            ->with(Mockery::type(SecuritySettingsDto::class))
            ->andReturn($payload);
        $this->app->instance(UsercpRepositoryInterface::class, $repository);

        $request = Request::create('/api/usercp/security', 'POST', ['current_password' => 'secret123']);
        $result = $this->app->make(UsercpController::class)->security($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }
}
