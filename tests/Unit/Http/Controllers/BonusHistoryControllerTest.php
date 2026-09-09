<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BonusHistoryController;
use App\Repositories\BonusCalculationRepository;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class BonusHistoryControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_controller_can_be_constructed_with_repository(): void
    {
        /** @var BonusCalculationRepository&Mockery\MockInterface $calculationRepository */
        $calculationRepository = Mockery::mock(BonusCalculationRepository::class);

        $controller = new BonusHistoryController($calculationRepository);

        $this->assertInstanceOf(BonusHistoryController::class, $controller);
    }
}
