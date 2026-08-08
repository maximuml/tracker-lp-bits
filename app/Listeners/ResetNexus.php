<?php

namespace App\Listeners;

use App\Support\SupportContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Nexus\Nexus;

class ResetNexus
{
    /**
     * Clear per-request legacy state so a worker/Octane process does not leak
     * values from one request/job into the next.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        SupportContext::reset();
        Nexus::flush();
    }
}
