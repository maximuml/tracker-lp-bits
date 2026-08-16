<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Nexus\Database\NexusDB;

/**
 * Append an X-Queries-Count header to every HTTP response.
 *
 * Uses the connection behind `NexusDB` so the count is correct whether the
 * request is handled by Laravel's DB facade (IN_NEXUS=false) or the legacy
 * Capsule connection (IN_NEXUS=true).
 */
final class AppendQueryCountHeader
{
    public function handle(RequestHandled $event): void
    {
        $count = count(NexusDB::eloquentConnection()->getQueryLog());

        $event->response->headers->set('X-Queries-Count', (string) $count);
    }
}
