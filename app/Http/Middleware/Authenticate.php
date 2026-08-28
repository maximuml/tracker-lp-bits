<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  mixed  $request
     */
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            return sprintf('%s/login.php?returnto=%s', $request->getSchemeAndHttpHost(), urlencode($request->fullUrl()));
        }

        return null;
    }
}
