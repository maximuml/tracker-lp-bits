<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cleanup\Tasks;
use Illuminate\Console\Command;

final class CleanupTasks extends Command
{
    /** @var string */
    protected $signature = 'cleanup:tasks {task : The cleanup task to run}';

    /** @var string */
    protected $description = 'Run a single cleanup task. Available tasks: prune-peers, reset-seed-bonus-counters, update-torrent-visibility, update-forum-counts, prune-offers, expire-torrent-promotions, expire-torrent-sticky, cleanup-stale-auth, disable-inactive-users, manage-user-classes, cleanup-dead-torrents';

    /** @var array<string, string> */
    private const TASKS = [
        'prune-peers' => 'prunePeers',
        'reset-seed-bonus-counters' => 'resetSeedBonusCounters',
        'update-torrent-visibility' => 'updateTorrentVisibility',
        'update-forum-counts' => 'updateForumCounts',
        'prune-offers' => 'pruneOffers',
        'expire-torrent-promotions' => 'expireTorrentPromotions',
        'expire-torrent-sticky' => 'expireTorrentSticky',
        'cleanup-stale-auth' => 'cleanupStaleAuth',
        'disable-inactive-users' => 'disableInactiveUsers',
        'manage-user-classes' => 'manageUserClasses',
        'cleanup-dead-torrents' => 'cleanupDeadTorrentsAndIpLogs',
    ];

    /**
     * @return int
     */
    public function handle(Tasks $tasks): int
    {
        $task = $this->argument('task');

        if (! is_string($task) || ! isset(self::TASKS[$task])) {
            $this->error("Unknown cleanup task: " . (is_string($task) ? $task : gettype($task)));

            return Command::FAILURE;
        }

        $lockKey = "cleanup:task:{$task}";
        $lock = new \Nexus\Database\NexusLock($lockKey, 3600);

        if (! $lock->acquire()) {
            $this->warn("Task {$task} is already running.");

            return Command::SUCCESS;
        }

        try {
            $log = $tasks->{self::TASKS[$task]}();
            $this->info($log);
        } catch (\Throwable $e) {
            $lock->release();
            $this->error("Task {$task} failed: " . $e->getMessage());
            do_log("cleanup task {$task} failed: " . $e->getMessage(), 'error');

            return Command::FAILURE;
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
