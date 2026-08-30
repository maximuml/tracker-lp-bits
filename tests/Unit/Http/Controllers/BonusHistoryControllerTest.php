<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BonusHistoryController;
use App\Repositories\BonusRepository;
use Mockery;
use Tests\TestCase;

final class BonusHistoryControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_controller_can_be_constructed_with_repository(): void
    {
        /** @var BonusRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(BonusRepository::class);

        $controller = new BonusHistoryController($repository);

        $this->assertInstanceOf(BonusHistoryController::class, $controller);
    }
}
