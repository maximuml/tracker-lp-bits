<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UserAdminController;
use App\Repositories\StaffDirectoryRepository;
use App\Repositories\UserListingRepository;
use App\Repositories\UserModerationRepository;
use App\Repositories\UserRepository;
use App\Services\BonusLedgerService;
use App\Services\PermissionChecker;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class UserAdminControllerTest extends TestCase
{
    use MakesCurrentUser;

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

        $ledger = $this->app->make(BonusLedgerService::class);

        $controller = new UserAdminController(
            Mockery::mock(PermissionChecker::class),
            Mockery::mock(StaffDirectoryRepository::class),
            $userRepository,
            $userModerationRepository,
            $ledger,
            Mockery::mock(UserListingRepository::class),
            $this->actor([]),
        );

        $this->assertInstanceOf(UserAdminController::class, $controller);
    }
}
