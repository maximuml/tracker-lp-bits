<?php

namespace App\Listeners;

use App\Models\Torrent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncTorrentToMeilisearch implements ShouldQueue
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
    public function handle($event): void
    {
        $torrent = $event->model ?? null;
        if (!$torrent instanceof Torrent) {
            do_log("event: " . get_class($event) . " no torrent model", 'error');
            return;
        }
        try {
            $torrent->refresh();
            if ($torrent->shouldBeSearchable()) {
                $torrent->searchable();
            }
            do_log("sync torrent to MeiliSearch: " . $torrent->id);
        } catch (\Throwable $e) {
            do_log('MeiliSearch sync listener failed: ' . $e->getMessage(), 'error');
        }
    }
}
