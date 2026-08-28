<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TorrentCreated;
use App\Models\Torrent;
use App\Repositories\UploadRepository;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailNotificationWhenTorrentCreated implements ShouldQueue
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
    public function handle(TorrentCreated $event): void
    {
        $torrent = $event->model;
        if (! $torrent instanceof Torrent) {
            Logger::writeWithContext((string) 'SendEmailNotificationWhenTorrentCreated: no torrent model', (string) 'error', (bool) false);

            return;
        }
        $uploadRepo = app(UploadRepository::class);
        $result = $uploadRepo->sendEmailNotification($torrent);
        Logger::writeWithContext((string) ("torrent: {$torrent->id}, sendEmailNotification result: ".var_export($result, true)), (string) 'info', (bool) false);
    }
}
