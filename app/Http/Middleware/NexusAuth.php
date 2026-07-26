<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class NexusAuth extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * @param  mixed  $request
     * @return  string|null
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            return sprintf("%s/login.php?returnto=%s", $request->getSchemeAndHttpHost(), urlencode($request->fullUrl()));
        }
        return null;
    }
}
