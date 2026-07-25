<?php

namespace App\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

/**
 * Generic legacy DB helpers extracted from `include/functions.php`.
 *
 * Backs the `sql_query()`, `sqlesc()`, `last_query()`, `hash_where()`,
 * `get_row_sum()` and `get_single_value()` procedural wrappers.
 *
 * These methods deliberately accept raw `$suffix` SQL because the legacy
 * callers pass unparameterised `WHERE`/`GROUP BY` fragments. The support
 * class only centralises the query shape and result extraction; the
 * wrappers remain responsible for any legacy sanitisation they already did.
 */
final class LegacyDb
{
    /**
     * Execute a legacy query, timing it and recording it in `$query_name`.
     *
     * Mirrors `sql_query()`.
     */
    public static function query(string $query): mixed
    {
        $begin = microtime(true);
        $result = NexusDB::getInstance()->query($query);
        $end = microtime(true);

        global $query_name;
        $query_name[] = [
            'query' => $query,
            'time' => sprintf('%.2f ms', ($end - $begin) * 1000),
        ];

        return $result;
    }

    /**
     * Escape and quote a scalar value for legacy SQL interpolation.
     *
     * Mirrors `sqlesc()`. Prefer prepared statements in new code.
     */
    public static function escape(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        return "'" . NexusDB::getInstance()->escapeString((string) $value) . "'";
    }

    /**
     * Return the raw query log, either the whole list or the last entry.
     *
     * Mirrors `last_query()`. `$all` may be the boolean `true` or the
     * literal string `'COUNT'` for the query-log count.
     */
    public static function lastQuery(bool|string $all = false, string $format = 'json'): mixed
    {
        static $connection;
        if (is_null($connection)) {
            $connectionName = NexusDB::getConnectionName();
            if (defined('IN_NEXUS') && IN_NEXUS) {
                $connection = Capsule::connection($connectionName);
            } else {
                $connection = DB::connection($connectionName);
            }
        }

        if ($all === 'COUNT') {
            return count($connection->getQueryLog());
        }

        $queries = $connection->getRawQueryLog();
        if ($all) {
            return $queries;
        }
        if (empty($queries)) {
            return '';
        }

        $last = last($queries);
        if ($format === 'json') {
            return Json::encode($last);
        }

        return $last;
    }

    /**
     * Build a WHERE clause fragment for a torrent info-hash.
     *
     * Mirrors `hash_where()`.
     */
    public static function hashWhere(string $name, string $hash): string
    {
        if (NexusDB::isMysql()) {
            return "$name = " . self::escape($hash);
        }
        if (NexusDB::isPgsql()) {
            return "$name = decode(bin2hex('$hash'), 'hex')";
        }

        throw new \RuntimeException('Not supported database');
    }

    /**
     * Return the scalar result of `SELECT SUM($field) FROM $table $suffix`.
     *
     * Mirrors `get_row_sum()`.
     */
    public static function sum(string $table, string $field, string $suffix = ''): mixed
    {
        $sql = "SELECT SUM($field) FROM $table $suffix";
        $result = NexusDB::getInstance()->query($sql);
        $row = NexusDB::getInstance()->fetchRow($result);

        return $row[0] ?? null;
    }

    /**
     * Return the first column of `SELECT $field FROM $table $suffix LIMIT 1`
     * or `false` if no row was found.
     *
     * Mirrors `get_single_value()`.
     */
    public static function singleValue(string $table, string $field, string $suffix = ''): mixed
    {
        $sql = "SELECT $field FROM $table $suffix LIMIT 1";
        $result = NexusDB::getInstance()->query($sql);
        $row = NexusDB::getInstance()->fetchRow($result);

        return $row ? $row[0] : false;
    }

    /**
     * Return the row count of `SELECT COUNT(*) FROM $table $suffix`.
     *
     * Mirrors `get_row_count()`.
     */
    public static function count(string $table, string $suffix = ''): int
    {
        $result = self::query("SELECT COUNT(*) FROM $table $suffix");
        $row = NexusDB::getInstance()->fetchRow($result);

        return (int) ($row[0] ?? 0);
    }

    /**
     * Fetch the most recent snatched row for a torrent/user pair.
     *
     * Mirrors `get_snatch_info()`.
     *
     * @return array<string, mixed>|false
     */
    public static function snatchInfo(int|string $torrentId, int|string $userId): array|false
    {
        $sql = sprintf(
            'SELECT * FROM snatched WHERE torrentid = %s AND userid = %s ORDER BY id DESC LIMIT 1',
            (int) $torrentId,
            (int) $userId,
        );

        return NexusDB::getInstance()->fetchAssoc(self::query($sql));
    }
}
