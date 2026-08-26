<?php

namespace App\Listeners;

use App\Support\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemoveSeedBoxRecordCache implements ShouldQueue
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
        Cache::forgetByPattern('nexus_is_ip_seed_box:ip:*');
    }
}
