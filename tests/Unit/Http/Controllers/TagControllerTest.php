<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Http\Controllers\TagController;
use App\Http\Requests\GenericIndexRequest;
use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class TagControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_delegates_to_repository_get_list(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 15, 1);

        /** @var TagRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepositoryInterface::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);
        $this->app->instance(TagRepositoryInterface::class, $repository);

        $request = GenericIndexRequest::create('/api/tags', 'GET', []);
        $request->setContainer($this->app);
        $request->validateResolved();

        $result = $this->app->make(TagController::class)->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_show_delegates_to_repository_get_detail(): void
    {
        $tag = tap(new Tag, fn (Tag $t) => $t->id = 4);

        /** @var TagRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepositoryInterface::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(4)
            ->andReturn($tag);
        $this->app->instance(TagRepositoryInterface::class, $repository);

        $result = $this->app->make(TagController::class)->show(4);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_delegates_to_repository(): void
    {
        /** @var TagRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TagRepositoryInterface::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(4)
            ->andReturn(true);
        $this->app->instance(TagRepositoryInterface::class, $repository);

        $result = $this->app->make(TagController::class)->destroy(4);

        $this->assertSame(0, $result['ret']);
    }
}
