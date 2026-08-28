<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\NexusException;
use App\Models\Setting;
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
        if ($user && $user->class < UserClassEnum::ADMINISTRATOR->value && ! Setting::getIsSiteOnline()) {
            throw new NexusException(Locale::trans('misc.site_down_for_maintenance', [], null));
        }

        return $next($request);
    }
}
