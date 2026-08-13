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
            \App\Support\Logger::writeWithContext((string) ("event: " . get_class($event) . " no torrent model"), (string) 'error', (bool) false);
            return;
        }
        try {
            $torrent->refresh();
            if ($torrent->shouldBeSearchable()) {
                $torrent->searchable();
            }
            \App\Support\Logger::writeWithContext((string) ("sync torrent to MeiliSearch: " . $torrent->id), (string) 'info', (bool) false);
        } catch (\Throwable $e) {
            \App\Support\Logger::writeWithContext((string) ('MeiliSearch sync listener failed: ' . $e->getMessage()), (string) 'error', (bool) false);
        }
    }
}
