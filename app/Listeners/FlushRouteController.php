<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Routing\Events\RouteMatched;

/**
 * Drop the route's memoized controller instance on every request.
 *
 * Route::getController() caches the resolved controller on the Route
 * object, and Route objects live for the application lifetime. In a
 * long-lived process (tests, Octane workers) a controller resolved on
 * request N — with per-request dependencies like ActorContext captured
 * in its constructor — would otherwise be reused verbatim on request
 * N+1 with a stale actor snapshot.
 */
final class FlushRouteController
{
    public function handle(RouteMatched $event): void
    {
        $event->route->flushController();
    }
}
