<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        /**
         * Just a test
         */
        $torrentNew = $event->model;
        $torrentOld = $event->modelOld;
        \App\Support\Logger::writeWithContext((string) sprintf("torrent: %d is updated, old descr: %s, new descr: %s", $torrentNew->id, $torrentOld->descr, $torrentNew->descr), (string) 'info', (bool) false);
    }
}
