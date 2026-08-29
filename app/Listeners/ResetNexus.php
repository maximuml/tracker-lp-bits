<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\AssetAppender;
use App\Support\CurrentUser;
use App\Support\PageLayout;
use App\Support\Permissions;
use App\Support\RequestContext;
use App\Support\SupportContext;
use App\Utils\MsgAlert;

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
        RequestContext::flush();
        AssetAppender::flush();
        PageLayout::resetState();
        Permissions::resetState();
        MsgAlert::resetState();
    }
}
