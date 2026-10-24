<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SearchBox as SearchBoxModel;
use App\Models\Setting;
use App\Repositories\CleanupRepository;
use App\Repositories\StyleRepository;
use App\Repositories\TagRepository;
use App\Support\Cache\NexusCache;
use App\Support\Category;
use App\Support\Comment;
use App\Support\Forum;
use App\Support\Permissions;
use App\Support\Promotion;
use App\Support\SearchBox;
use App\Support\Settings;
use App\Support\Shoutbox;
use App\Support\Style;
use App\Support\UserClass;
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
     * AssetAppender, MsgAlert, TurnstileRenderState, ToolMaintenanceService)
     * is dropped by forgetScopedInstances() — invoked by the queue
     * resetScope callback between jobs — no manual resets needed here.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        Permissions::resetState();
        UserDisplay::resetState();
        Category::resetState();
        Style::resetState();

        // T-10: Drop all resolved guard instances to prevent cross-request
        // and cross-job user leakage in long-lived workers.
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

        // Per-request memoized caches that used to live in function-static
        // variables or class statics (invisible under FPM's per-request
        // lifecycle, worker-global under Octane/RoadRunner).
        Forum::resetState();
        Promotion::resetState();
        Shoutbox::resetState();
        UserClass::resetState();
        TagRepository::resetState();
        StyleRepository::resetState();
        SearchBox::resetState();
        CleanupRepository::resetState();
        Comment::resetTempCode();
        // The NexusCache singleton keeps its Redis connection for the
        // worker lifetime, but the ?clearcache bypass flag and the debug
        // read/write counters are per-request and must not accumulate.
        if ($this->app->resolved(NexusCache::class)) {
            $this->app->make(NexusCache::class)->reset();
        }
        Setting::resetState();
        SearchBoxModel::resetState();
        SecondIconResource::resetState();
    }
}
