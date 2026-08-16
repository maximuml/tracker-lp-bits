<?php

namespace App\Listeners;

use App\Models\Torrent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;

class ClearTorrentCache implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

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
            $infoHash = (string) Torrent::query()->where('id', $torrentId)->value('info_hash');
            \App\Support\Cache::clearTorrent($infoHash);
            \App\Support\Logger::writeWithContext((string) ("success clear torrent: {$torrentId} cache with info_hash: " . rawurlencode($infoHash)), (string) 'info', (bool) false);
        } else {
            \App\Support\Logger::writeWithContext((string) "no torrent id", (string) 'error', (bool) false);
        }
    }
}
