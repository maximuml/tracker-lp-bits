<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T-23: Restricts /metrics access to internal networks or valid bearer tokens.
 *
 * Access is granted if ANY of the following conditions are met:
 * 1. The request comes from a private/internal IP (10.x, 172.16-31.x, 192.168.x, 127.x, ::1)
 * 2. A valid bearer token is provided via the Authorization header
 *    (configured via METRICS_TOKEN env var)
 * 3. The METRICS_TOKEN env var is empty/unset (open access — for dev only)
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

        // If no token is configured, allow from private networks only
        if ($token === '') {
            if ($this->isPrivateNetwork($request)) {
                return $next($request);
            }

            return response('Metrics access denied: internal network or bearer token required', 403, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        // Check bearer token
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $provided = trim(substr($authHeader, 7));
            if (hash_equals($token, $provided)) {
                return $next($request);
            }
        }

        // Also allow private network without token
        if ($this->isPrivateNetwork($request)) {
            return $next($request);
        }

        return response('Metrics access denied: invalid or missing bearer token', 403, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    private function isPrivateNetwork(Request $request): bool
    {
        $ip = $request->ip();
        if ($ip === null) {
            return false;
        }

        // Check IPv4 loopback
        if ($ip === '127.0.0.1') {
            return true;
        }

        // Check IPv6 loopback
        if ($ip === '::1') {
            return true;
        }

        foreach (self::PRIVATE_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            // IPv6 or invalid — skip CIDR check for IPv4 ranges
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
