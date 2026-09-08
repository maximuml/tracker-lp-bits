<?php

declare(strict_types=1);

namespace App\Support\Network;

use App\Support\Network;

/**
 * Resolves the client IP address from the request environment.
 *
 * Wraps the static Network::clientIp() method so it can be injected
 * and mocked in unit tests without touching $_SERVER.
 */
class ClientIpResolver
{
    /**
     * Resolve the real client IP (first address in the chain).
     */
    public function resolve(): string
    {
        return Network::clientIp(true);
    }

    /**
     * Resolve the full IP chain (comma-separated, includes proxies).
     */
    public function resolveChain(): string
    {
        return Network::clientIp(false);
    }
}
