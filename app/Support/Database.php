<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Database introspection and SQL-dialect helpers extracted from `NexusDB`.
 *
 * The helpers here mirror the driver-aware behaviour of `NexusDB`'s static
 * methods, branching on the configured connection driver (`mysql` or `pgsql`).
 */
final class Database
{
    /**
     * Return the database server version, minimum supported version, and
     * whether the running version meets the minimum.
     *
     * Mirrors `NexusDB::getDatabaseVersionInfo()`.
     *
     * @return array{version: string, match: bool, minVersion: string, dbType: string|null}
     */
    public static function versionInfo(): array
    {
        $version = (string) DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $dbType = Config::get('nexus.database.default', null);
        $minVersion = match ($dbType) {
            'mysql' => '5.7.8',
            'pgsql' => '16.0',
            default => throw new \RuntimeException('Not supported database.'),
        };
        $match = version_compare($version, $minVersion, '>=');

        return compact('version', 'match', 'minVersion', 'dbType');
    }

    /**
     * Build a SQL fragment that converts a datetime column to a unix timestamp.
     *
     * Mirrors `NexusDB::unixTimestampField()`.
     */
    public static function unixTimestampField(string $field): string
    {
        return match (self::driverName()) {
            'mysql' => sprintf('UNIX_TIMESTAMP(%s)', $field),
            'pgsql' => sprintf('EXTRACT(EPOCH FROM %s)', $field),
            default => throw new \RuntimeException('Not supported database.'),
        };
    }

    /**
     * Build a SQL fragment that converts a unix timestamp to a datetime.
     *
     * Mirrors `NexusDB::fromUnixTimestampField()`.
     */
    public static function fromUnixTimestampField(int $timestamp): string
    {
        return match (self::driverName()) {
            'mysql' => sprintf('FROM_UNIXTIME(%d)', $timestamp),
            'pgsql' => sprintf('to_timestamp(%d)', $timestamp),
            default => throw new \RuntimeException('Not supported database.'),
        };
    }

    /**
     * Build the upsert suffix for a raw INSERT statement.
     *
     * Mirrors `NexusDB::upsertField()`.
     *
     * @param  list<string>  $uniqueFields
     * @param  list<string>  $updateFields
     */
    public static function upsertField(array $uniqueFields, array $updateFields = []): string
    {
        return match (self::driverName()) {
            'mysql' => sprintf(
                'ON DUPLICATE KEY UPDATE %s',
                implode(', ', array_map(
                    static fn (string $field): string => "`$field` = VALUES(`$field`)",
                    $updateFields ?: ['id'],
                )),
            ),
            'pgsql' => sprintf(
                'ON CONFLICT (%s) DO %s',
                implode(', ', $uniqueFields),
                empty($updateFields)
                    ? 'NOTHING'
                    : 'UPDATE SET '.implode(', ', array_map(
                        static fn (string $field): string => "$field = EXCLUDED.$field",
                        $updateFields,
                    )),
            ),
            default => throw new \RuntimeException('Not supported database.'),
        };
    }

    /**
     * Build a SQL fragment that aggregates a column into a comma-separated list.
     *
     * Mirrors `NexusDB::groupConcatField()`.
     */
    public static function groupConcatField(string $field): string
    {
        return match (self::driverName()) {
            'mysql' => sprintf('group_concat(%s)', $field),
            'pgsql' => sprintf("string_agg(%s::text, ',')", $field),
            default => throw new \RuntimeException('Not supported database.'),
        };
    }

    /**
     * Return the configured database driver name.
     */
    private static function driverName(): string
    {
        return (string) DB::connection()->getDriverName();
    }
}
