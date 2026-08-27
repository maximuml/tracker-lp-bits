<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Append an ``X-Response-Time`` header (in milliseconds) to every response.
 *
 * Complements the existing ``X-Queries-Count`` header so operators can
 * correlate latency with query volume without external tooling.
 */
final class ResponseTimeHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        $response = $next($request);

        $elapsedNs = hrtime(true) - $start;
        $elapsedMs = (int) ($elapsedNs / 1_000_000);

        $response->headers->set('X-Response-Time', $elapsedMs.'ms');

        return $response;
    }
}
