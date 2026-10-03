<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Enums\ExamIndex;
use App\Http\Controllers\ExamController;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class ExamControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_destroy_delegates_to_repository(): void
    {
        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(3)
            ->andReturn(true);
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $result = $this->app->make(ExamController::class)->destroy(3);

        $this->assertSame(0, $result['ret']);
    }

    public function test_indexes_delegates_to_repository(): void
    {
        $payload = [['index' => ExamIndex::UPLOADED->value, 'name' => 'Uploaded']];

        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('listIndexes')
            ->once()
            ->andReturn($payload);
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $result = $this->app->make(ExamController::class)->indexes();

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }
}
