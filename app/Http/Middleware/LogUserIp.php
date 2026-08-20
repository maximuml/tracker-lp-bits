<?php

namespace App\Http\Middleware;

use App\Repositories\IpLogRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserIp
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (\App\Support\Environment::isTesting()) {
            return $response;
        }
        $user = $request->user();
        if ($user) {
            IpLogRepository::saveToCache($user->id);
        }
        return $response;
    }
}
