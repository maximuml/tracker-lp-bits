<?php

namespace App\Listeners;

use App\Events\TorrentCreated;
use App\Repositories\UploadRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        $uploadRepo = new UploadRepository();
        $result = $uploadRepo->sendEmailNotification($torrent);
        \App\Support\Logger::writeWithContext((string) ("torrent: {$torrent->id}, sendEmailNotification result: " . var_export($result, true)), (string) 'info', (bool) false);
    }
}
