<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Services\Cleanup\Tasks\ForumMaintenanceTask;
use App\Services\Cleanup\Tasks\OfferCleanupTask;
use App\Services\Cleanup\Tasks\PeriodicHousekeepingTask;

/**
 * Parameter object bundling the site-content cleanup tasks — keeps the
 * Tasks coordinator's constructor within the RepositorySizeTest cap.
 */
final readonly class SiteCleanupTasks
{
    public function __construct(
        public ForumMaintenanceTask $forumMaintenance,
        public OfferCleanupTask $offerCleanup,
        public PeriodicHousekeepingTask $periodicHousekeeping,
    ) {}
}
