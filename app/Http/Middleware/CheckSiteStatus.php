<?php

namespace App\Http\Middleware;

use App\Exceptions\NexusException;
use App\Models\Setting;
use App\Models\User;
use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSiteStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->class < User::CLASS_ADMINISTRATOR && ! Setting::getIsSiteOnline()) {
            throw new NexusException(Locale::trans('misc.site_down_for_maintenance', [], null));
        }

        return $next($request);
    }
}
