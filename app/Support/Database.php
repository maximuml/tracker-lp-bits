<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Database introspection helpers extracted from `NexusDB`.
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
}
