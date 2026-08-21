<?php

namespace App\Listeners;

use App\Support\SupportContext;
use Nexus\Nexus;

class ResetNexus
{
    /**
     * Clear per-request legacy state so a worker/Octane process does not leak
     * values from one request/job into the next.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        SupportContext::reset();
        Nexus::flush();
    }
}
