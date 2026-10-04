<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

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
        private readonly TorrentCleanupTasks $torrentTasks,
        private readonly AccountCleanupTasks $accountTasks,
        private readonly SiteCleanupTasks $siteTasks,
    ) {}

    /**
     * Priority Class 1: remove peers whose last_action is older than the dead
     * threshold.
     */
    public function prunePeers(): string
    {
        return $this->torrentTasks->peerCleanup->prunePeers();
    }

    /**
     * Priority Class 1: reset per-hour seed bonus counters for users whose seeding
     * snapshot is older than two autoclean intervals.
     */
    public function resetSeedBonusCounters(): string
    {
        return $this->torrentTasks->peerCleanup->resetSeedBonusCounters();
    }

    /**
     * Priority Class 2: mark torrents with no seeders and stale last_action as
     * invisible.
     */
    public function updateTorrentVisibility(): string
    {
        return $this->torrentTasks->torrentVisibility->updateTorrentVisibility();
    }

    /**
     * Priority Class 3: recompute post/topic counts for every forum.
     */
    public function updateForumCounts(): string
    {
        return $this->siteTasks->forumMaintenance->updateForumCounts();
    }

    /**
     * Priority Class 3: delete offers that were never voted on and offers that
     * were approved but never uploaded.
     */
    public function pruneOffers(): string
    {
        return $this->siteTasks->offerCleanup->pruneOffers();
    }

    /**
     * Priority Class 3: expire time-based global torrent promotions.
     */
    public function expireTorrentPromotions(): string
    {
        return $this->torrentTasks->torrentPromotionCleanup->expireTorrentPromotions();
    }

    /**
     * Priority Class 3: expire sticky position states.
     */
    public function expireTorrentSticky(): string
    {
        return $this->torrentTasks->torrentVisibility->expireTorrentSticky();
    }

    /**
     * Priority Class 4: delete unconfirmed accounts, old login attempts, invite
     * codes and regimage records.
     */
    public function cleanupStaleAuth(): string
    {
        return $this->accountTasks->staleAuthCleanup->cleanupStaleAuth();
    }

    /**
     * Priority Class 4: disable or destroy inactive user accounts.
     */
    public function disableInactiveUsers(): string
    {
        return $this->accountTasks->inactiveUserCleanup->disableInactiveUsers();
    }

    /**
     * Priority Class 4: promote/demote users and ban leech-warning expiries.
     */
    public function manageUserClasses(): string
    {
        return $this->accountTasks->userClassManagement->manageUserClasses();
    }

    /**
     * Priority Class 4: delete dead torrents, old IP logs, and stale failed jobs.
     */
    public function cleanupDeadTorrentsAndIpLogs(): string
    {
        return $this->torrentTasks->deadTorrentAndLogCleanup->cleanupDeadTorrentsAndIpLogs();
    }

    /**
     * Priority Class 5: cleanup tasks that run every 15 days.
     */
    public function cleanupClass5(): string
    {
        return $this->siteTasks->periodicHousekeeping->cleanupClass5();
    }
}
