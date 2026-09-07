<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Prune old records from activity_log, iplog, and login_logs tables.
 *
 * W3-06: Runs daily in the maintenance queue. Retention:
 *   activity_log: 90 days
 *   iplog:        180 days
 *   login_logs:   180 days
 */
final class PruneActivityLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<string, int> */
    private const RETENTION = [
        'activity_log' => 90,
        'iplog' => 180,
        'login_logs' => 180,
    ];

    public function handle(): void
    {
        foreach (self::RETENTION as $table => $days) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $column = $this->dateColumn($table);
            $cutoff = now()->subDays($days);

            DB::table($table)
                ->where($column, '<', $cutoff->toDateTimeString())
                ->delete();
        }
    }

    private function dateColumn(string $table): string
    {
        return match ($table) {
            'activity_log' => 'created_at',
            'iplog' => 'access',
            'login_logs' => 'created_at',
            default => 'created_at',
        };
    }
}
