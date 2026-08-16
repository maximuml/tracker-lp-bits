<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
     *
     * @param  object  $event
     * @return void
     */
    public function handle(object $event): void
    {
        if (! property_exists($event, 'data') || ! is_array($event->data)) {
            \App\Support\Logger::writeWithContext((string) 'DeductUserBonusWhenTorrentDeleted: no data', (string) 'error', (bool) false);
            return;
        }
        $torrent = $event->data;
        \App\Support\Logger::writeWithContext((string) sprintf("torrent: %d is deleted, and it's pieces_hash is: %s", (int) ($torrent['id'] ?? 0), (string) ($torrent['pieces_hash'] ?? '')), (string) 'info', (bool) false);
    }
}
