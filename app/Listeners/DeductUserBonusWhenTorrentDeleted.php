<?php

namespace App\Listeners;

use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeductUserBonusWhenTorrentDeleted implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
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
        if (! property_exists($event, 'data') || ! is_array($event->data)) {
            Logger::writeWithContext((string) 'DeductUserBonusWhenTorrentDeleted: no data', (string) 'error', (bool) false);

            return;
        }
        $torrent = $event->data;
        Logger::writeWithContext((string) sprintf("torrent: %d is deleted, and it's pieces_hash is: %s", (int) ($torrent['id'] ?? 0), (string) ($torrent['pieces_hash'] ?? '')), (string) 'info', (bool) false);
    }
}
