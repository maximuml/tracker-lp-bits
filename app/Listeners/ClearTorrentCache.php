<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Repositories\TorrentDetailRepository;
use App\Support\Cache;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

class ClearTorrentCache implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    /**
     * Create the event listener.
     */
    public function __construct(private readonly TorrentDetailRepository $torrentDetailRepository) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $torrentId = 0;
        if (property_exists($event, 'model') && $event->model instanceof Model) {
            $torrentId = (int) $event->model->getKey();
        }
        if ($torrentId > 0) {
            $infoHash = (string) $this->torrentDetailRepository->valueInfoHash($torrentId);
            Cache::clearTorrent($infoHash);
            Logger::writeWithContext((string) ("success clear torrent: {$torrentId} cache with info_hash: ".rawurlencode($infoHash)), (string) 'info', (bool) false);
        } else {
            Logger::writeWithContext((string) 'no torrent id', (string) 'error', (bool) false);
        }
    }
}
