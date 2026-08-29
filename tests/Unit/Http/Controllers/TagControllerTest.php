<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TagController;
use App\Models\Tag;
use App\Repositories\TagRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class TagControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_tags(): void
    {
        $tag = new Tag;
        $tag->id = 1;
        $tag->name = 'Test';
        $tag->color = '#ff0000';

        $collection = new Collection([$tag]);

        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($collection);

        $controller = new TagController($repository);
        $request = Request::create('/api/v1/tags', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_tag(): void
    {
        $tag = new Tag;
        $tag->id = 1;
        $tag->name = 'Test';
        $tag->color = '#ff0000';

        $data = ['name' => 'Test', 'color' => '#ff0000'];

        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn($tag);

        $controller = new TagController($repository);
        $request = Request::create('/api/v1/tags', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new TagController($repository);
        $request = Request::create('/api/v1/tags', 'POST', []);

        $controller->store($request);
    }

    public function test_update_modifies_tag(): void
    {
        $tag = new Tag;
        $tag->id = 1;
        $tag->name = 'Updated';
        $tag->color = '#00ff00';

        $data = ['name' => 'Updated', 'color' => '#00ff00', 'priority' => '5'];

        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['name'] === $data['name']
                    && $arg['color'] === $data['color']
                    && $arg['priority'] === 5;
            }), 1)
            ->andReturn($tag);

        $controller = new TagController($repository);
        $request = Request::create('/api/v1/tags/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldNotReceive('update');

        $controller = new TagController($repository);
        $request = Request::create('/api/v1/tags/1', 'PUT', []);

        $controller->update($request, 1);
    }

    public function test_destroy_removes_tag(): void
    {
        /** @var TagRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new TagController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }
}
