<?php

namespace App\Listeners;

use App\Repositories\SearchRepository;
use App\Repositories\ToolRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncTorrentToElasticsearch implements ShouldQueue
{

    public $tries = 3;

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
        $id = $event->model?->id ?? 0;
        if ($id == 0) {
            \App\Support\Logger::writeWithContext((string) ("event: " . get_class($event) . " no model id"), (string) 'error', (bool) false);
            return;
        }
        $searchRep = new SearchRepository();
        $result = $searchRep->updateTorrent($id);
        \App\Support\Logger::writeWithContext((string) sprintf("updateTorrent: %s result: %s", $id, var_export($result, true)), (string) 'info', (bool) false);

    }

    /**
     * handle failed
     *
     * @param  object  $event
     * @return void
     */
    public function failed($event, \Throwable $exception)
    {
        $toolRep = new ToolRepository();
        $to = \App\Support\Config\SiteConfig::current()->main->siteEmail();
        $subject = sprintf('Event: %s listener: %s handle error', get_class($event), __CLASS__);
        $body = sprintf("%s\n%s", $exception->getMessage(), $exception->getTraceAsString());
        try {
            $result = $toolRep->sendMail($to, $subject, $body);
            if ($result === false) {
                \App\Support\Logger::writeWithContext((string) "{$subject} send mail fail", (string) 'alert', (bool) false);
            }
        } catch (\Throwable $exception) {
            \App\Support\Logger::writeWithContext((string) ("{$subject} send mail fail: " . $exception->getMessage() . $exception->getTraceAsString()), (string) 'alert', (bool) false);
        }
    }
}
