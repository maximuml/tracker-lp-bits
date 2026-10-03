<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Http\Controllers\ShoutboxController;
use Illuminate\Http\Request;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class ShoutboxControllerIndexTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_delegates_to_repository_history(): void
    {
        $request = Request::create('/api/shoutbox', 'GET', ['page' => 1]);
        $payload = ['items' => [['id' => 7, 'text' => 'hi']]];

        /** @var ShoutboxRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ShoutboxRepositoryInterface::class);
        $repository->shouldReceive('history')
            ->once()
            ->with($request)
            ->andReturn($payload);
        $this->app->instance(ShoutboxRepositoryInterface::class, $repository);

        $result = $this->app->make(ShoutboxController::class)->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }
}
