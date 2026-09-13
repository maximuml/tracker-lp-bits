<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-23: Restricts /metrics access to internal networks or valid bearer tokens.
 *
 * Access rules:
 * - Production: a valid METRICS_TOKEN bearer is MANDATORY (W6-04).
 *   Private-network exemption is dev-only: behind a reverse proxy
 *   REMOTE_ADDR is always the proxy's private IP, so "private" cannot be
 *   trusted. If METRICS_TOKEN is unset in production the endpoint fails
 *   closed (403 for everyone) rather than silently opening.
 * - Non-production: private/internal IP, or a valid bearer token when
 *   METRICS_TOKEN is configured.
 */
final class MetricsAccess
{
    private const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1/128',
        'fc00::/7',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('metrics.token', '');

        if (App::isProduction()) {
            if ($token === '') {
                logger()->warning('metrics: METRICS_TOKEN unset in production — endpoint closed');

                return $this->deny('Metrics access denied: bearer token required');
            }

            if ($this->bearerMatches($request, $token)) {
                return $next($request);
            }

            return $this->deny('Metrics access denied: invalid or missing bearer token');
        }

        if ($token !== '' && $this->bearerMatches($request, $token)) {
            return $next($request);
        }

        if ($this->isPrivateNetwork($request)) {
            return $next($request);
        }

        return $this->deny($token === ''
            ? 'Metrics access denied: internal network or bearer token required'
            : 'Metrics access denied: invalid or missing bearer token');
    }

    private function bearerMatches(Request $request, string $token): bool
    {
        $authHeader = $request->header('Authorization', '');
        if (! str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        return hash_equals($token, trim(substr($authHeader, 7)));
    }

    private function deny(string $message): Response
    {
        return response($message, 403, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    private function isPrivateNetwork(Request $request): bool
    {
        $ip = $request->ip();
        if ($ip === null) {
            return false;
        }

        return IpUtils::checkIp($ip, self::PRIVATE_RANGES);
    }
}
