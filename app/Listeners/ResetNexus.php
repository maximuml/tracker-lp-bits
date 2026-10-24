<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Cache\NexusCache;
use App\Support\UserDisplay;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;

class ResetNexus
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Clear per-request legacy state so a worker process does not leak
     * values from one request/job into the next.
     *
     * Container `#[Scoped]`/scoped-bound state (CurrentUser, ActorContext,
     * HeaderBag, PageRenderer, RuntimeContext, RequestContext, NexusContext,
     * AssetAppender, MsgAlert, TurnstileRenderState, ToolMaintenanceService,
     * plus the App\Support\State\* holders behind the Permissions, Category,
     * Style, Settings, Forum, Promotion, Shoutbox, UserClass, SearchBox,
     * Comment, TagRepository, StyleRepository, CleanupRepository, Setting,
     * SearchBox-model and SecondIconResource facades) is dropped by
     * forgetScopedInstances() — invoked by the queue resetScope callback
     * between jobs — no manual resets needed here.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        // UserDisplay::resetState() stays explicit: besides clearing its
        // in-process row/username caches it also invalidates the Redis
        // "user_{id}_content" entries, which container scoping cannot do.
        UserDisplay::resetState();

        // T-10: Drop all resolved guard instances to prevent cross-request
        // and cross-job user leakage in long-lived workers.
        // NexusWebGuard caches $this->user on the guard instance, which
        // persists while AuthManager keeps the resolved guard. Resetting
        // only the default 'web' guard is not enough — the app authenticates
        // via 'nexus-web' (and 'nexus'/'passkey' on tracker routes), so
        // forgetGuards() clears every resolved guard instance at once.
        Auth::forgetGuards();

        // The NexusCache singleton keeps its Redis connection for the
        // worker lifetime, but the ?clearcache bypass flag and the debug
        // read/write counters are per-request and must not accumulate.
        if ($this->app->resolved(NexusCache::class)) {
            $this->app->make(NexusCache::class)->reset();
        }
    }
}
