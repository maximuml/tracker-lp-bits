<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\AssetAppender;
use App\Support\CurrentUser;
use App\Support\PageLayout;
use App\Support\Permissions;
use App\Support\RequestContext;
use App\Support\Settings;
use App\Support\SupportContext;
use App\Utils\MsgAlert;
use Illuminate\Support\Facades\Auth;

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

        // T-10: Reset auth guard cached user to prevent cross-request user
        // leakage under Octane. NexusWebGuard caches $this->user on the guard
        // instance, which persists across worker requests unless explicitly
        // cleared. We use a reflection-based approach because setUser(null)
        // is not supported by all guard implementations (e.g. SessionGuard
        // requires a non-null Authenticatable).
        $guard = Auth::guard();
        if (property_exists($guard, 'user')) {
            try {
                $ref = new \ReflectionProperty($guard, 'user');
                $ref->setAccessible(true);
                $ref->setValue($guard, null);
            } catch (\Throwable) {
                // Guard may not have a $user property — skip silently
            }
        }

        // T-10: Reset Settings static cache so workers reload settings from DB
        // instead of serving stale values for the worker's lifetime. This is
        // critical for settings that affect security (e.g. login_secret,
        // enabled features) or per-user behavior.
        Settings::resetCache();
    }
}
