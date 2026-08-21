<?php

namespace App\Http\Middleware;

use App\Repositories\IpLogRepository;
use App\Support\Environment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserIp
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (Environment::isTesting()) {
            return $response;
        }
        $user = $request->user();
        if ($user) {
            IpLogRepository::saveToCache($user->id);
        }

        return $response;
    }
}
