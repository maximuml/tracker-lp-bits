<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\MedalController;
use App\Models\Medal;
use App\Repositories\MedalRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class MedalControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_medals(): void
    {
        $medal = new Medal;
        $medal->id = 1;
        $medal->name = 'Test Medal';
        $medal->price = 100;
        $medal->get_type = 0;
        $medal->image_large = 'https://example.com/large.png';
        $medal->image_small = 'https://example.com/small.png';
        $medal->duration = 30;
        $medal->description = 'Test';

        $paginator = new LengthAwarePaginator([$medal], 1, 15, 1);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);

        $controller = new MedalController($repository);
        $request = Request::create('/api/v1/medals', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_medal(): void
    {
        $medal = new Medal;
        $medal->id = 1;
        $medal->name = 'Test Medal';
        $medal->price = 100;
        $medal->get_type = 0;
        $medal->image_large = 'https://example.com/large.png';
        $medal->image_small = 'https://example.com/small.png';
        $medal->duration = 30;
        $medal->description = 'Test';

        $data = [
            'name' => 'Test Medal',
            'price' => 100,
            'image_large' => 'https://example.com/large.png',
            'image_small' => 'https://example.com/small.png',
        ];

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->andReturn($medal);

        $controller = new MedalController($repository);
        $request = Request::create('/api/v1/medals', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new MedalController($repository);
        $request = Request::create('/api/v1/medals', 'POST', []);

        $controller->store($request);
    }

    public function test_store_validates_image_must_be_url(): void
    {
        $this->expectException(ValidationException::class);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new MedalController($repository);
        $request = Request::create('/api/v1/medals', 'POST', [
            'name' => 'Test',
            'price' => 100,
            'image_large' => 'not-a-url',
            'image_small' => 'not-a-url',
        ]);

        $controller->store($request);
    }

    public function test_show_returns_medal_detail(): void
    {
        $medal = new Medal;
        $medal->id = 1;
        $medal->name = 'Test Medal';
        $medal->price = 100;
        $medal->get_type = 0;
        $medal->image_large = 'https://example.com/large.png';
        $medal->image_small = 'https://example.com/small.png';
        $medal->duration = 30;
        $medal->description = 'Test';

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(1)
            ->andReturn($medal);

        $controller = new MedalController($repository);

        $result = $controller->show(1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_modifies_medal(): void
    {
        $medal = new Medal;
        $medal->id = 1;
        $medal->name = 'Updated Medal';
        $medal->price = 200;
        $medal->get_type = 0;
        $medal->image_large = 'https://example.com/large.png';
        $medal->image_small = 'https://example.com/small.png';
        $medal->duration = 30;
        $medal->description = 'Test';

        $data = [
            'name' => 'Updated Medal',
            'price' => 200,
            'image_large' => 'https://example.com/large.png',
            'image_small' => 'https://example.com/small.png',
        ];

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 1)
            ->andReturn($medal);

        $controller = new MedalController($repository);
        $request = Request::create('/api/v1/medals/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_removes_medal(): void
    {
        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new MedalController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }
}
