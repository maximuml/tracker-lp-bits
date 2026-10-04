<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\CleanupMonitorRepositoryInterface;
use App\Repositories\IpLogRepository;
use App\Repositories\MessageRepository;
use App\Repositories\TorrentCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Log;
use App\Support\TorrentOps;

/**
 * Priority Class 4: delete dead torrents, old IP logs, and stale failed jobs.
 */
final class DeadTorrentAndLogCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly CleanupMonitorRepositoryInterface $cleanupMonitor,
        private readonly TorrentCleanupRepository $torrentCleanup,
        private readonly IpLogRepository $ipLogRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    /**
     * Priority Class 4: delete dead torrents, old IP logs, and stale failed jobs.
     */
    public function cleanupDeadTorrentsAndIpLogs(): string
    {
        $this->deleteDeadTorrents();
        $this->deleteOldIpLogs();
        $this->deleteFailedJobs();

        return 'delete dead torrents, old IP logs and failed jobs';
    }

    // ------------------------------------------------------------------------
    // Dead torrent / IP log helpers
    // ------------------------------------------------------------------------

    private function deleteDeadTorrents(): void
    {
        $days = (int) SiteConfig::current()->torrent->delDeadTorrent(0);
        if ($days <= 0) {
            return;
        }

        $length = $days * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);
        $dt = date('Y-m-d H:i:s');

        $res = $this->torrentCleanup->listDeadTorrentsForDeletion($until);

        foreach ($res as $torrent) {
            $arr = (array) $torrent;

            TorrentOps::deleteTorrents((int) $arr['id']);

            if (! empty($arr['uid'])) {
                $locale = Locale::userLocale((int) $arr['owner']);

                $this->messageRepository->insertMessages([[
                    'sender' => null,
                    'receiver' => $arr['owner'],
                    'added' => $dt,
                    'subject' => Locale::trans('cleanup.msg_your_torrent_deleted', [], $locale),
                    'msg' => Locale::trans('cleanup.msg_your_torrent', [], $locale)
                        .'[i]'.$arr['name'].'[/i]'
                        .Locale::trans('cleanup.msg_was_deleted_because_dead', [], $locale),
                ]]);

                Log::write("Torrent {$arr['id']} ({$arr['name']}) is deleted by system because of being dead for a long time.", 'normal');
            }
        }
    }

    private function deleteOldIpLogs(): void
    {
        $length = 90 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->ipLogRepository->deleteBefore($until);
    }

    private function deleteFailedJobs(): void
    {
        $length = 10 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->cleanupMonitor->deleteFailedJobsBefore($until);
    }

    public function run(): string
    {
        return $this->cleanupDeadTorrentsAndIpLogs();
    }
}
