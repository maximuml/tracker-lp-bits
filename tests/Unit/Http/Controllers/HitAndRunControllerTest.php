<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\HitAndRunStatus;
use App\Http\Controllers\HitAndRunController;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class HitAndRunControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_hit_and_runs(): void
    {
        $hitAndRun = new HitAndRun;
        $hitAndRun->id = 1;
        $hitAndRun->uid = 5;
        $hitAndRun->torrent_id = 10;
        $hitAndRun->status = HitAndRunStatus::UNREACHED->value;

        $collection = new Collection([$hitAndRun]);

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($collection);

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_hit_and_run(): void
    {
        $hitAndRun = new HitAndRun;
        $hitAndRun->id = 1;
        $hitAndRun->uid = 5;
        $hitAndRun->torrent_id = 10;
        $hitAndRun->status = HitAndRunStatus::UNREACHED->value;

        $data = [
            'family_id' => 1,
            'name' => 'Test',
            'peer_id' => 'peer123',
            'agent' => 'uTorrent',
            'comment' => 'Test comment',
        ];

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn($hitAndRun);

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs', 'POST', []);

        $controller->store($request);
    }

    public function test_show_returns_hit_and_run_detail(): void
    {
        $hitAndRun = new HitAndRun;
        $hitAndRun->id = 1;
        $hitAndRun->uid = 5;
        $hitAndRun->torrent_id = 10;

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(1)
            ->andReturn($hitAndRun);

        $controller = new HitAndRunController($repository);

        $result = $controller->show(1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_modifies_hit_and_run(): void
    {
        $hitAndRun = new HitAndRun;
        $hitAndRun->id = 1;
        $hitAndRun->uid = 5;
        $hitAndRun->torrent_id = 10;
        $hitAndRun->status = HitAndRunStatus::PARDONED->value;

        $data = [
            'family_id' => 1,
            'name' => 'Updated',
            'peer_id' => 'peer123',
            'agent' => 'uTorrent',
            'comment' => 'Updated comment',
        ];

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 1)
            ->andReturn($hitAndRun);

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_removes_hit_and_run(): void
    {
        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new HitAndRunController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }

    public function test_list_status_returns_status_list(): void
    {
        $statusList = [
            ['value' => 0, 'label' => 'Unreached'],
            ['value' => 1, 'label' => 'Reached'],
        ];

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('listStatus')
            ->once()
            ->andReturn($statusList);

        $controller = new HitAndRunController($repository);

        $result = $controller->listStatus();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_pardon_returns_false_when_not_authenticated(): void
    {
        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldNotReceive('pardon');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new HitAndRunController($repository);

        $result = $controller->pardon(1);

        $this->assertSame(0, $result['ret']);
        $this->assertFalse($result['data']['result']);
    }

    public function test_pardon_succeeds_for_authenticated_user(): void
    {
        $user = new User;
        $user->id = 5;

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('pardon')
            ->once()
            ->with(1, Mockery::type(User::class))
            ->andReturn(true);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new HitAndRunController($repository);

        $result = $controller->pardon(1);

        $this->assertSame(0, $result['ret']);
        $this->assertTrue($result['data']);
    }

    public function test_bulk_pardon_returns_affected_count(): void
    {
        $user = new User;
        $user->id = 5;

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('bulkPardon')
            ->once()
            ->with(['ids' => [1, 2, 3]], Mockery::type(User::class))
            ->andReturn(3);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs/bulk-pardon', 'POST', ['ids' => [1, 2, 3]]);

        $result = $controller->bulkPardon($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(3, $result['data']['result']);
    }

    public function test_bulk_delete_returns_affected_count(): void
    {
        $user = new User;
        $user->id = 5;

        /** @var HitAndRunRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(HitAndRunRepository::class);
        $repository->shouldReceive('bulkDelete')
            ->once()
            ->with(['ids' => [1, 2]], Mockery::type(User::class))
            ->andReturn(2);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new HitAndRunController($repository);
        $request = Request::create('/api/v1/hit-and-runs/bulk-delete', 'POST', ['ids' => [1, 2]]);

        $result = $controller->bulkDelete($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(2, $result['data']['result']);
    }
}
