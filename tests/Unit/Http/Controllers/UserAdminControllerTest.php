<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UserAdminController;
use App\Repositories\BonusRepository;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class UserAdminControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_controller_can_be_constructed_with_dependencies(): void
    {
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        /** @var UserModerationRepository&Mockery\MockInterface $userModerationRepository */
        $userModerationRepository = Mockery::mock(UserModerationRepository::class);

        /** @var BonusRepository&Mockery\MockInterface $bonusRepository */
        $bonusRepository = Mockery::mock(BonusRepository::class);

        $controller = new UserAdminController($userRepository, $userModerationRepository, $bonusRepository);

        $this->assertInstanceOf(UserAdminController::class, $controller);
    }
}
