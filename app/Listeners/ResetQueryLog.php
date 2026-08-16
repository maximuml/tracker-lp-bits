<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Nexus\Database\NexusDB;

/**
 * Reset the query log at the start of each request.
 *
 * Uses the connection behind `NexusDB` so the count stays accurate whether
 * the request is running through Laravel's DB facade (IN_NEXUS=false) or
 * the legacy Capsule connection (IN_NEXUS=true).
 */
final class ResetQueryLog
{
    public function handle(RouteMatched $event): void
    {
        NexusDB::eloquentConnection()->flushQueryLog();
    }
}
