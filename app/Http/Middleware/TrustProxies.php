<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Use the same `TRUSTED_PROXIES` list as the legacy `Network::clientIp()`
     * resolver so Laravel requests and procedural pages agree on the
     * originating client IP.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = config('nexus.trusted_proxies', '*');

        // An empty or whitespace-only value means "trust no proxy".
        // Only the explicit wildcard '*' means "trust the calling peer".
        if ($proxies === '' || $proxies === null) {
            return null;
        }

        if (is_string($proxies) && trim($proxies) === '') {
            return null;
        }

        return $proxies;
    }

}
