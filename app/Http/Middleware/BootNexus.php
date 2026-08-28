<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexus\Nexus;

class BootNexus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        Nexus::boot();

        //        do_log(sprintf(
        //            "Nexus booted. request.server: %s, request.header: %s, request.query: %s, request.input: %s",
        //            nexus_json_encode($request->server()), nexus_json_encode($request->header()), nexus_json_encode($request->query()), nexus_json_encode($request->input())
        //        ));
        return $next($request);
    }
}
