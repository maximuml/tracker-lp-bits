<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the mysql_stats page (mysql_stats/index.blade.php).
 *
 * Assembled by SystemMaintenanceController::mysqlStats().
 */
final class MysqlStatsPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<int, mixed>>  $queryStatColumns
     * @param  array<int, array<int, mixed>>  $statusColumns
     */
    public function __construct(
        public readonly string $serverRunningText,
        public readonly string $receivedTotal,
        public readonly string $receivedPerHour,
        public readonly string $sentTotal,
        public readonly string $sentPerHour,
        public readonly string $totalBytesTotal,
        public readonly string $totalBytesPerHour,
        public readonly string $abortedConnects,
        public readonly string $abortedConnectsPerHour,
        public readonly string $abortedConnectsPct,
        public readonly string $abortedClients,
        public readonly string $abortedClientsPerHour,
        public readonly string $abortedClientsPct,
        public readonly string $connectionsTotal,
        public readonly string $connectionsPerHour,
        public readonly string $questionsTotal,
        public readonly string $questionsPerHour,
        public readonly string $questionsPerMinute,
        public readonly string $questionsPerSecond,
        public readonly array $queryStatColumns,
        public readonly array $statusColumns,
        public readonly bool $hasServerStatus,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['serverRunningText', 'receivedTotal', 'receivedPerHour', 'sentTotal', 'sentPerHour', 'totalBytesTotal', 'totalBytesPerHour', 'abortedConnects', 'abortedConnectsPerHour', 'abortedConnectsPct', 'abortedClients', 'abortedClientsPerHour', 'abortedClientsPct', 'connectionsTotal', 'connectionsPerHour', 'questionsTotal', 'questionsPerHour', 'questionsPerMinute', 'questionsPerSecond', 'queryStatColumns', 'statusColumns', 'hasServerStatus'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
