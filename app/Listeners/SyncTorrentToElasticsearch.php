<?php

namespace App\Listeners;

use App\Repositories\SearchRepository;
use App\Repositories\ToolRepository;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

class SyncTorrentToElasticsearch implements ShouldQueue
{
    public int $tries = 3;

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
        $id = 0;
        if (property_exists($event, 'model') && $event->model instanceof Model) {
            $id = (int) $event->model->getKey();
        }
        if ($id == 0) {
            Logger::writeWithContext((string) ('event: '.get_class($event).' no model id'), (string) 'error', (bool) false);

            return;
        }
        $searchRep = new SearchRepository;
        $result = $searchRep->updateTorrent($id);
        Logger::writeWithContext((string) sprintf('updateTorrent: %s result: %s', $id, var_export($result, true)), (string) 'info', (bool) false);

    }

    /**
     * handle failed
     */
    public function failed(object $event, \Throwable $exception): void
    {
        $toolRep = new ToolRepository;
        $to = SiteConfig::current()->main->siteEmail();
        $subject = sprintf('Event: %s listener: %s handle error', get_class($event), __CLASS__);
        $body = sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString());
        try {
            $result = $toolRep->sendMail($to, $subject, $body);
            if ($result === false) {
                Logger::writeWithContext((string) "{$subject} send mail fail", (string) 'alert', (bool) false);
            }
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ("{$subject} send mail fail: ".$exception->getMessage().$exception->getTraceAsString()), (string) 'alert', (bool) false);
        }
    }
}
