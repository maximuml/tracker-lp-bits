<?php

namespace App\Listeners;

use App\Support\CurrentUser;
use App\Support\PageLayout;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Utils\MsgAlert;
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
        app(CurrentUser::class)->reset();
        Nexus::flush();
        PageLayout::resetState();
        Permissions::resetState();
        MsgAlert::resetState();
    }
}
