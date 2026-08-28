<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protect the /cron endpoint from unauthenticated external access.
 *
 * The cron endpoint triggers cleanup tasks and must not be callable by
 * arbitrary visitors. Access is granted when either:
 *  1. The request comes from loopback (127.0.0.1 / ::1), or
 *  2. The request carries a valid `token` query parameter matching
 *     the `CRON_TOKEN` environment variable.
 *
 * If `CRON_TOKEN` is not set (empty), only loopback requests are allowed.
 */
final class CronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isLoopback($request)) {
            return $next($request);
        }

        $expectedToken = (string) config('app.cron_token', '');

        if ($expectedToken !== '') {
            $providedToken = (string) $request->query('token', '');

            if ($providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
                return $next($request);
            }
        }

        return response('Forbidden', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function isLoopback(Request $request): bool
    {
        $ip = $request->ip();

        return $ip === '127.0.0.1' || $ip === '::1';
    }
}
