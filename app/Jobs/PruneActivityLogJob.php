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
 *
 * For iplog and login_logs, old records are archived to *_archive
 * tables before deletion (monthly partitioning alternative — MySQL
 * native partitioning does not support the foreign keys these tables
 * have).
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

    /** @var array<string, string> */
    private const ARCHIVE_TABLES = [
        'iplog' => 'iplog_archive',
        'login_logs' => 'login_logs_archive',
    ];

    public function handle(): void
    {
        foreach (self::RETENTION as $table => $days) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $column = $this->dateColumn($table);
            $cutoff = now()->subDays($days);

            // Archive old records before deletion (for tables with archive tables)
            if (isset(self::ARCHIVE_TABLES[$table]) && DB::getSchemaBuilder()->hasTable(self::ARCHIVE_TABLES[$table])) {
                $this->archiveRecords($table, self::ARCHIVE_TABLES[$table], $column, $cutoff->toDateTimeString());
            }

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

    /**
     * Copy old records into the archive table before they are pruned.
     * Uses INSERT ... SELECT to avoid loading rows into PHP memory.
     */
    private function archiveRecords(string $source, string $archive, string $column, string $cutoff): void
    {
        $columns = $this->archiveColumns($source);

        // Insert into archive, adding archived_at timestamp
        $columnList = implode(', ', $columns);
        $selectList = implode(', ', $columns);

        DB::statement(
            "INSERT INTO `{$archive}` ({$columnList}, `archived_at`) "
            ."SELECT {$selectList}, NOW() FROM `{$source}` WHERE `{$column}` < ?",
            [$cutoff],
        );
    }

    /**
     * Get the column list for a given source table (excluding auto-increment id).
     *
     * @return list<string>
     */
    private function archiveColumns(string $table): array
    {
        return match ($table) {
            'iplog' => ['`id`', '`ip`', '`userid`', '`access`', '`uri`', '`count`'],
            'login_logs' => ['`id`', '`uid`', '`ip`', '`country`', '`city`', '`client`', '`created_at`', '`updated_at`'],
            default => ['`id`'],
        };
    }
}
