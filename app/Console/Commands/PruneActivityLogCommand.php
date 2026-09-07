<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prune old records from activity_log, iplog, and login_logs tables.
 *
 * W3-06: Retention policy — 90 days for activity_log, 180 days for
 * iplog and login_logs. Runs in the maintenance queue by default.
 */
final class PruneActivityLogCommand extends Command
{
    protected $signature = 'log:prune
                            {--days= : Override retention days (default: activity_log=90, iplog=180, login_logs=180)}
                            {--table= : Prune only the specified table}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Prune old records from activity_log, iplog, and login_logs tables';

    /** @var array<string, int> */
    private const DEFAULT_RETENTION = [
        'activity_log' => 90,
        'iplog' => 180,
        'login_logs' => 180,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyTable = $this->option('table');
        $overrideDays = $this->option('days') !== null ? (int) $this->option('days') : null;

        $tables = $onlyTable !== null
            ? [$onlyTable => $overrideDays ?? self::DEFAULT_RETENTION[$onlyTable] ?? 90]
            : self::DEFAULT_RETENTION;

        if ($overrideDays !== null) {
            $tables = array_map(fn () => $overrideDays, $tables);
        }

        $totalDeleted = 0;

        foreach ($tables as $table => $days) {
            if (! $this->tableExists($table)) {
                $this->warn("Table '{$table}' does not exist, skipping.");

                continue;
            }

            $cutoff = now()->subDays($days)->toDateTimeString();
            $count = DB::table($table)
                ->where($this->getDateColumn($table), '<', $cutoff)
                ->count();

            if ($count === 0) {
                $this->info("{$table}: 0 records older than {$days} days (cutoff: {$cutoff})");

                continue;
            }

            if ($dryRun) {
                $this->info("{$table}: would delete {$count} records older than {$days} days (cutoff: {$cutoff}) [dry-run]");
            } else {
                $deleted = DB::table($table)
                    ->where($this->getDateColumn($table), '<', $cutoff)
                    ->delete();
                $this->info("{$table}: deleted {$deleted} records older than {$days} days (cutoff: {$cutoff})");
                $totalDeleted += $deleted;
            }
        }

        $this->info($dryRun
            ? 'Dry run complete. No records were deleted.'
            : "Prune complete. Total deleted: {$totalDeleted}");

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function getDateColumn(string $table): string
    {
        return match ($table) {
            'activity_log' => 'created_at',
            'iplog' => 'access',
            'login_logs' => 'created_at',
            default => 'created_at',
        };
    }
}
