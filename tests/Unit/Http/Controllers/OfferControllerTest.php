<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Http\Controllers\OfferController;
use Illuminate\Http\Request;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class OfferControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_delegates_to_repository_list(): void
    {
        $request = Request::create('/api/offers', 'GET', ['page' => 2]);
        $payload = ['items' => [['id' => 1]], 'total' => 1];

        /** @var OfferRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(OfferRepositoryInterface::class);
        $repository->shouldReceive('list')
            ->once()
            ->with($request)
            ->andReturn($payload);
        $this->app->instance(OfferRepositoryInterface::class, $repository);

        $result = $this->app->make(OfferController::class)->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }
}
