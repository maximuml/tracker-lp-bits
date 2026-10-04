<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Services\Cleanup\Tasks\InactiveUserCleanupTask;
use App\Services\Cleanup\Tasks\StaleAuthCleanupTask;
use App\Services\Cleanup\Tasks\UserClassManagementTask;

/**
 * Parameter object bundling the account lifecycle cleanup tasks — keeps the
 * Tasks coordinator's constructor within the RepositorySizeTest cap.
 */
final readonly class AccountCleanupTasks
{
    public function __construct(
        public StaleAuthCleanupTask $staleAuthCleanup,
        public InactiveUserCleanupTask $inactiveUserCleanup,
        public UserClassManagementTask $userClassManagement,
    ) {}
}
