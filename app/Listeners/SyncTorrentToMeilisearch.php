<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Torrent;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncTorrentToMeilisearch implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

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
        $torrent = property_exists($event, 'model') && $event->model instanceof Torrent ? $event->model : null;
        if (! $torrent instanceof Torrent) {
            Logger::writeWithContext((string) ('event: '.$event::class.' no torrent model'), (string) 'error', (bool) false);

            return;
        }
        try {
            $torrent->refresh();
            if ($torrent->shouldBeSearchable()) {
                $torrent->searchable();
            }
            Logger::writeWithContext((string) ('sync torrent to MeiliSearch: '.$torrent->id), (string) 'info', (bool) false);
        } catch (\Throwable $e) {
            Logger::writeWithContext((string) ('MeiliSearch sync listener failed: '.$e->getMessage()), (string) 'error', (bool) false);
        }
    }
}
