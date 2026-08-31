<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Services\Cleanup\Tasks\DeadTorrentAndLogCleanupTask;
use App\Services\Cleanup\Tasks\ForumMaintenanceTask;
use App\Services\Cleanup\Tasks\InactiveUserCleanupTask;
use App\Services\Cleanup\Tasks\OfferCleanupTask;
use App\Services\Cleanup\Tasks\PeerCleanupTask;
use App\Services\Cleanup\Tasks\PeriodicHousekeepingTask;
use App\Services\Cleanup\Tasks\StaleAuthCleanupTask;
use App\Services\Cleanup\Tasks\TorrentPromotionCleanupTask;
use App\Services\Cleanup\Tasks\TorrentVisibilityTask;
use App\Services\Cleanup\Tasks\UserClassManagementTask;

/**
 * Thin coordinator that delegates to individual cleanup task classes.
 *
 * Each public method forwards to the corresponding task class, preserving
 * the original method names so that CleanupService and the cleanup:tasks
 * command require no changes. Callers are responsible for locking and
 * scheduling.
 */
final class Tasks
{
    public function __construct(
        private readonly PeerCleanupTask $peerCleanup,
        private readonly TorrentVisibilityTask $torrentVisibility,
        private readonly ForumMaintenanceTask $forumMaintenance,
        private readonly OfferCleanupTask $offerCleanup,
        private readonly TorrentPromotionCleanupTask $torrentPromotionCleanup,
        private readonly StaleAuthCleanupTask $staleAuthCleanup,
        private readonly InactiveUserCleanupTask $inactiveUserCleanup,
        private readonly UserClassManagementTask $userClassManagement,
        private readonly DeadTorrentAndLogCleanupTask $deadTorrentAndLogCleanup,
        private readonly PeriodicHousekeepingTask $periodicHousekeeping,
    ) {}

    /**
     * Priority Class 1: remove peers whose last_action is older than the dead
     * threshold.
     */
    public function prunePeers(): string
    {
        return $this->peerCleanup->prunePeers();
    }

    /**
     * Priority Class 1: reset per-hour seed bonus counters for users whose seeding
     * snapshot is older than two autoclean intervals.
     */
    public function resetSeedBonusCounters(): string
    {
        return $this->peerCleanup->resetSeedBonusCounters();
    }

    /**
     * Priority Class 2: mark torrents with no seeders and stale last_action as
     * invisible.
     */
    public function updateTorrentVisibility(): string
    {
        return $this->torrentVisibility->updateTorrentVisibility();
    }

    /**
     * Priority Class 3: recompute post/topic counts for every forum.
     */
    public function updateForumCounts(): string
    {
        return $this->forumMaintenance->updateForumCounts();
    }

    /**
     * Priority Class 3: delete offers that were never voted on and offers that
     * were approved but never uploaded.
     */
    public function pruneOffers(): string
    {
        return $this->offerCleanup->pruneOffers();
    }

    /**
     * Priority Class 3: expire time-based global torrent promotions.
     */
    public function expireTorrentPromotions(): string
    {
        return $this->torrentPromotionCleanup->expireTorrentPromotions();
    }

    /**
     * Priority Class 3: expire sticky position states.
     */
    public function expireTorrentSticky(): string
    {
        return $this->torrentVisibility->expireTorrentSticky();
    }

    /**
     * Priority Class 4: delete unconfirmed accounts, old login attempts, invite
     * codes and regimage records.
     */
    public function cleanupStaleAuth(): string
    {
        return $this->staleAuthCleanup->cleanupStaleAuth();
    }

    /**
     * Priority Class 4: disable or destroy inactive user accounts.
     */
    public function disableInactiveUsers(): string
    {
        return $this->inactiveUserCleanup->disableInactiveUsers();
    }

    /**
     * Priority Class 4: promote/demote users and ban leech-warning expiries.
     */
    public function manageUserClasses(): string
    {
        return $this->userClassManagement->manageUserClasses();
    }

    /**
     * Priority Class 4: delete dead torrents, old IP logs, and stale failed jobs.
     */
    public function cleanupDeadTorrentsAndIpLogs(): string
    {
        return $this->deadTorrentAndLogCleanup->cleanupDeadTorrentsAndIpLogs();
    }

    /**
     * Priority Class 5: cleanup tasks that run every 15 days.
     */
    public function cleanupClass5(): string
    {
        return $this->periodicHousekeeping->cleanupClass5();
    }
}
