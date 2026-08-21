<?php

namespace App\Listeners;

use App\Events\TorrentCreated;
use App\Models\Torrent;
use App\Repositories\UploadRepository;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailNotificationWhenTorrentCreated implements ShouldQueue
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
    public function handle(TorrentCreated $event): void
    {
        $torrent = $event->model;
        if (! $torrent instanceof Torrent) {
            Logger::writeWithContext((string) 'SendEmailNotificationWhenTorrentCreated: no torrent model', (string) 'error', (bool) false);

            return;
        }
        $uploadRepo = new UploadRepository;
        $result = $uploadRepo->sendEmailNotification($torrent);
        Logger::writeWithContext((string) ("torrent: {$torrent->id}, sendEmailNotification result: ".var_export($result, true)), (string) 'info', (bool) false);
    }
}
