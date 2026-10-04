<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Retention queries over the rotating activity-log tables (iplog,
 * login_logs, ...) — shared by the prune job and the prune command.
 * Table and date-column names are caller-supplied because the retention
 * map is config-driven.
 */
final class ActivityLogRepository
{
    public function countOlderThan(string $table, string $dateColumn, string $cutoff): int
    {
        return (int) DB::table($table)
            ->where($dateColumn, '<', $cutoff)
            ->count();
    }

    public function deleteOlderThan(string $table, string $dateColumn, string $cutoff): int
    {
        return (int) DB::table($table)
            ->where($dateColumn, '<', $cutoff)
            ->delete();
    }
}
