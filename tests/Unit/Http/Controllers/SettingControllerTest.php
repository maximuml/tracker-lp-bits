<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\SettingController;
use App\Repositories\SettingRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class SettingControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_settings_list(): void
    {
        $settings = ['hr' => ['inspect_time' => 72]];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($settings);

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_index_returns_filtered_settings_by_prefix(): void
    {
        $settings = ['hr' => ['inspect_time' => 72]];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with(['prefix' => 'hr'])
            ->andReturn($settings);

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings', 'GET', ['prefix' => 'hr']);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_saves_hr_settings(): void
    {
        $data = [
            'hr' => [
                'ban_user_when_counts_reach' => 3,
                'ignore_when_ratio_reach' => 1.0,
                'inspect_time' => 72,
                'seed_time_minimum' => 24,
                'mode' => 'manual',
            ],
        ];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn(true);

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_store_validates_hr_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings', 'POST', [
            'hr' => ['inspect_time' => 72],
        ]);

        $controller->store($request);
    }

    public function test_store_validates_hr_mode_enum(): void
    {
        $this->expectException(ValidationException::class);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings', 'POST', [
            'hr' => [
                'ban_user_when_counts_reach' => 3,
                'ignore_when_ratio_reach' => 1.0,
                'inspect_time' => 72,
                'seed_time_minimum' => 24,
                'mode' => 'invalid-mode',
            ],
        ]);

        $controller->store($request);
    }

    public function test_show_returns_empty_array(): void
    {
        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);

        $controller = new SettingController($repository);

        $result = $controller->show(1);

        $this->assertSame([], $result);
    }

    public function test_update_returns_empty_array(): void
    {
        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);

        $controller = new SettingController($repository);
        $request = Request::create('/api/v1/settings/1', 'PUT', []);

        $result = $controller->update($request, 1);

        $this->assertSame([], $result);
    }

    public function test_destroy_returns_empty_array(): void
    {
        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);

        $controller = new SettingController($repository);

        $result = $controller->destroy(1);

        $this->assertSame([], $result);
    }
}
