<?php

declare(strict_types=1);

namespace App\Listeners;

use App\DTOs\Auth\ActorContext;
use App\Support\AssetAppender;
use App\Support\Category;
use App\Support\CurrentUser;
use App\Support\HeaderBag;
use App\Support\NexusContext;
use App\Support\PageRenderer;
use App\Support\Permissions;
use App\Support\RequestContext;
use App\Support\RuntimeContext;
use App\Support\Settings;
use App\Support\Style;
use App\Support\UserDisplay;
use App\Utils\MsgAlert;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;

class ResetNexus
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly HeaderBag $headerBag,
        private readonly Application $app,
    ) {}

    /**
     * Clear per-request legacy state so a worker/Octane process does not leak
     * values from one request/job into the next.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        NexusContext::reset();
        $this->currentUser->reset();
        // T-20: Flush the ActorContext singleton so the next request
        // re-resolves it from the freshly-reset auth guard.
        $this->app->forgetInstance(ActorContext::class);
        RequestContext::flush();
        AssetAppender::flush();
        $this->app->forgetInstance(PageRenderer::class);
        Permissions::resetState();
        MsgAlert::resetState();
        UserDisplay::resetState();
        Category::resetState();
        Style::resetState();
        // T-11: Flush the per-request legacy header bag so headers/status
        // set by one request do not leak into the next under Octane.
        $this->headerBag->flush();
        // ADR 0017: restore entry-point legacy/tracker flags — per-request
        // code may mutate them under Octane.
        $this->app->make(RuntimeContext::class)->reset();

        // T-10: Drop all resolved guard instances to prevent cross-request
        // and cross-job user leakage under Octane and the queue worker.
        // NexusWebGuard caches $this->user on the guard instance, which
        // persists while AuthManager keeps the resolved guard. Resetting
        // only the default 'web' guard is not enough — the app authenticates
        // via 'nexus-web' (and 'nexus'/'passkey' on tracker routes), so
        // forgetGuards() clears every resolved guard instance at once.
        Auth::forgetGuards();

        // T-10: Reset Settings static cache so workers reload settings from DB
        // instead of serving stale values for the worker's lifetime. This is
        // critical for settings that affect security (e.g. login_secret,
        // enabled features) or per-user behavior.
        Settings::resetCache();
    }
}
