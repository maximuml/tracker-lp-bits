<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;

class ThrottleRequests extends BaseThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (config('nexus.rate_limiting') === false) {
            return $next($request);
        }

        // Forward the exact arguments: the parent distinguishes named
        // limiters (throttle:name → 3 args) from inline ones
        // (throttle:60,1 → 4 args) via func_num_args().
        return parent::handle(...func_get_args());
    }
}
