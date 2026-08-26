<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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
        DB::connection(Config::get('nexus.database.default', null))->flushQueryLog();
    }
}
