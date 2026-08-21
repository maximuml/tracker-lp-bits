<?php

namespace App\Listeners;

use App\Models\Torrent;
use App\Support\Logger;

class TestTorrentUpdated
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
        /**
         * Just a test
         */
        $torrentNew = $event->model ?? null;
        $torrentOld = $event->modelOld ?? null;
        if (! $torrentNew instanceof Torrent || ! $torrentOld instanceof Torrent) {
            Logger::writeWithContext((string) 'TestTorrentUpdated: missing models', (string) 'error', (bool) false);

            return;
        }
        Logger::writeWithContext((string) sprintf('torrent: %d is updated, old descr: %s, new descr: %s', $torrentNew->id, $torrentOld->descr, $torrentNew->descr), (string) 'info', (bool) false);
    }
}
