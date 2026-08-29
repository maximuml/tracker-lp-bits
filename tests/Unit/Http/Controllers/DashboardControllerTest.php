<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_system_info_returns_system_data(): void
    {
        $systemInfo = ['nexus_version' => ['name' => 'nexus_version', 'value' => '1.8.0']];

        /** @var DashboardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(DashboardRepository::class);
        $repository->shouldReceive('getSystemInfo')
            ->once()
            ->andReturn($systemInfo);

        $controller = new DashboardController($repository);

        $result = $controller->systemInfo();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_stat_data_returns_statistics(): void
    {
        $statData = [
            'user_class' => ['text' => 'User class', 'data' => []],
        ];

        /** @var DashboardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(DashboardRepository::class);
        $repository->shouldReceive('getStatData')
            ->once()
            ->andReturn($statData);

        $controller = new DashboardController($repository);

        $result = $controller->statData();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_latest_user_returns_user_collection(): void
    {
        $user = new User;
        $user->id = 1;
        $user->username = 'testuser';

        $collection = new Collection([$user]);

        /** @var DashboardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(DashboardRepository::class);
        $repository->shouldReceive('latestUser')
            ->once()
            ->andReturn($collection);

        $controller = new DashboardController($repository);

        $result = $controller->latestUser();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_latest_torrent_returns_torrent_collection(): void
    {
        /** @var DashboardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(DashboardRepository::class);
        $repository->shouldReceive('latestTorrent')
            ->once()
            ->andReturn(new Collection([]));

        $controller = new DashboardController($repository);

        $result = $controller->latestTorrent();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }
}
