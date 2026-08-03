<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    /**
     * Return a JSON 401 for API/AJAX requests instead of redirecting.
     *
     * @param  array<int, string>  $guards
     */
    protected function unauthenticated($request, array $guards): void
    {
        if ($request->expectsJson() || $request->ajax()) {
            throw new HttpResponseException(
                response()->json(['ret' => -1, 'msg' => 'Not login!'], 401)
            );
        }

        parent::unauthenticated($request, $guards);
    }
}
