<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CleanupRepository;
use App\Services\Cleanup\Tasks;
use App\Support\SupportContext;
use Nexus\Database\NexusDB;

/**
 * Cleanup orchestrator. Replaces the monolithic `docleanup()` with discrete,
 * idempotent task methods and Redis-locked dispatch.
 */
final class CleanupService
{
    /** @var array<int, array<int, array<string, string>>> */
    private const CLASSES = [
        1 => [
            ['task' => 'prune-peers', 'method' => 'prunePeers'],
            ['task' => 'reset-seed-bonus-counters', 'method' => 'resetSeedBonusCounters'],
        ],
        2 => [
            ['task' => 'update-torrent-visibility', 'method' => 'updateTorrentVisibility'],
        ],
        3 => [
            ['task' => 'update-forum-counts', 'method' => 'updateForumCounts'],
            ['task' => 'prune-offers', 'method' => 'pruneOffers'],
            ['task' => 'expire-torrent-promotions', 'method' => 'expireTorrentPromotions'],
            ['task' => 'expire-torrent-sticky', 'method' => 'expireTorrentSticky'],
        ],
        4 => [
            ['task' => 'cleanup-stale-auth', 'method' => 'cleanupStaleAuth'],
            ['task' => 'disable-inactive-users', 'method' => 'disableInactiveUsers'],
            ['task' => 'manage-user-classes', 'method' => 'manageUserClasses'],
            ['task' => 'cleanup-dead-torrents', 'method' => 'cleanupDeadTorrentsAndIpLogs'],
        ],
    ];

    public function __construct(
        private readonly Tasks $tasks,
    ) {}

    /**
     * Trigger the periodic (cron) cleanup if cron-triggered cleanup is enabled.
     *
     * Mirrors the legacy `public/cron.php` + `resources/views/cron/_cron_legacy.php`.
     */
    public function triggerCron(): string
    {
        $useCronTriggerCleanUp = (bool) SupportContext::getGlobal('useCronTriggerCleanUp', true);

        if (! $useCronTriggerCleanUp) {
            return "Forbidden. Clean-up is set to be browser-triggered.\n";
        }

        $result = $this->runAll(false, false);

        if ($result === false || $result === '') {
            return "Clean-up not triggered.\n";
        }

        return (string) $result . "\n";
    }

    /**
     * Run the full cleanup routine (sysop-only manual trigger).
     *
     * Mirrors the legacy `docleanup.php` flow. The returned string already
     * contains the progress HTML emitted by `docleanup()`.
     */
    public function runFull(bool $forceAll = false, bool $printProgress = true): string
    {
        if (! \app()->runningInConsole() && \get_user_class() < \constant('UC_SYSOP')) {
            return 'forbidden';
        }

        $tstart = \getmicrotime();

        $progress = $this->runAll($forceAll, $printProgress);

        $tend = \getmicrotime();

        $html = '<html><head><title>Do Clean-up</title></head><body>';
        $html .= '<p>clean-up in progress...please wait<br />';
        if (! $forceAll) {
            $html .= 'If you need to force a complete cleaning, click <a href="docleanup.php?forceall=1">here</a><br />';
        }
        $html .= '</p>';
        $html .= nl2br(htmlspecialchars((string) $progress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $html .= sprintf('<p>Time consumed：%f sec<br /></p>', $tend - $tstart);
        $html .= 'Done<br />';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Run all cleanup priority classes, gating by legacy `lastcleantime*` values.
     *
     * @return string|false The progress log, or false when the first class is not due.
     */
    public function runAll(bool $forceAll = false, bool $printProgress = false): string|bool
    {
        $now = time();
        $requestId = nexus()->getRequestId();
        $output = '';

        foreach (self::CLASSES as $level => $taskList) {
            $arg = $this->getLastCleanArg($level);
            $interval = (int) get_setting("main.autoclean_interval_{$this->intervalName($level)}", 0);

            if (! $forceAll) {
                $ts = (int) NexusDB::table('avps')->where('arg', $arg)->value('value_u');

                if ($ts === 0) {
                    NexusDB::table('avps')->insertOrIgnore(['arg' => $arg, 'value_u' => $now]);
                    do_log("no value for arg: '{$arg}', return");

                    return false;
                }

                if ($ts + $interval > $now) {
                    $log = "Cleanup ends at Priority Class " . ($level - 1);
                    do_log("{$log}, {$ts} + {$interval} > {$now}");

                    return $log;
                }

                $claimed = (int) NexusDB::table('avps')
                    ->where('arg', $arg)
                    ->where('value_u', $ts)
                    ->update(['value_u' => $now]);

                if ($claimed === 0) {
                    do_log("cleanup class {$level} already claimed by another runner");

                    return false;
                }
            } else {
                NexusDB::table('avps')->updateOrInsert(['arg' => $arg], ['value_u' => $now]);
            }

            $output = $this->runClass($level, $taskList, $requestId, $output, $printProgress);
        }

        $output .= "Full cleanup is done\n";

        return $output;
    }

    /**
     * @param array<int, array<string, string>> $taskList
     */
    private function runClass(int $level, array $taskList, string $requestId, string $output, bool $printProgress): string
    {
        foreach ($taskList as $item) {
            $task = $item['task'];
            $method = $item['method'];
            $lockKey = "cleanup:task:{$task}";
            $lockTtl = max(60, (int) get_setting("main.autoclean_interval_{$this->intervalName($level)}", 3600));
            $lock = new \Nexus\Database\NexusLock($lockKey, $lockTtl);

            if (! $lock->acquire()) {
                $msg = "Task {$task} is already running.";
                do_log($msg, 'warning');
                if ($printProgress) {
                    $output .= $msg . "\n";
                }
                continue;
            }

            try {
                $log = $this->tasks->{$method}();
                if ($printProgress) {
                    $output .= $this->formatProgress($log);
                }
                do_log($log);
            } catch (\Throwable $e) {
                $lock->release();
                do_log("cleanup task {$task} failed: " . $e->getMessage(), 'error');
                if ($printProgress) {
                    $output .= "Task {$task} failed: " . $e->getMessage() . "\n";
                }
                continue;
            } finally {
                $lock->release();
            }
        }

        // Class-specific batch jobs are kept in their existing repository form.
        if ($level === 1) {
            CleanupRepository::runBatchJobCalculateUserSeedBonus($requestId);
        } elseif ($level === 3) {
            CleanupRepository::runBatchJobUpdateTorrentSeedersEtc($requestId);
        } elseif ($level === 4) {
            CleanupRepository::runBatchJobUpdateUserSeedingLeechingTime($requestId);
        }

        return $output;
    }

    private function getLastCleanArg(int $level): string
    {
        return $level === 1 ? 'lastcleantime' : "lastcleantime{$level}";
    }

    private function intervalName(int $level): string
    {
        return match ($level) {
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            default => 'one',
        };
    }

    private function formatProgress(string $message): string
    {
        return sprintf("[%s] [%s] %s ... done!\n", date('Y-m-d H:i:s'), nexus()->getRequestId(), $message);
    }
}
