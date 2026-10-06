<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\BonusHistoryController;
use App\Repositories\BonusCalculationRepository;
use App\Repositories\UserListingRepository;
use App\Support\CurrentUser;
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

        $controller = new BonusHistoryController(
            Mockery::mock(TorrentRepositoryInterface::class),
            Mockery::mock(UserListingRepository::class),
            Mockery::mock(UserRepositoryInterface::class),
            $calculationRepository,
            new CurrentUser,
        );

        $this->assertInstanceOf(BonusHistoryController::class, $controller);
    }
}
