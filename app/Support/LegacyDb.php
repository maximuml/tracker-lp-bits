<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Generic legacy DB helpers extracted from `include/functions.php` and
 * `include/globalfunctions.php`.
 *
 * Backs `last_query()` and `get_snatch_info()`. All raw-query helpers
 * (`sql_query`, `sqlesc`, `get_row_count`, etc.) have been replaced by
 * `NexusDB` QueryBuilder calls.
 */
final class LegacyDb
{
    /**
     * Return the last database error message.
     *
     * Mirrors `mysql_error()`.
     */
    public static function error(): string
    {
        return (string) NexusDB::getInstance()->error();
    }

    /**
     * Return the raw query log, either the whole list or the last entry.
     *
     * Mirrors `last_query()`. `$all` may be the boolean `true` or the
     * literal string `'COUNT'` for the query-log count.
     */
    public static function lastQuery(bool|string $all = false, string $format = 'json'): mixed
    {
        $connection = NexusDB::eloquentConnection();

        if ($all === 'COUNT') {
            return count($connection->getQueryLog());
        }

        $grammar = $connection->getQueryGrammar();
        $queries = array_map(static function (array $log) use ($grammar) {
            $bindings = array_map(static function ($binding) {
                if (is_string($binding) && preg_match('//u', $binding) === false) {
                    return '<binary:' . bin2hex($binding) . '>';
                }
                if (is_resource($binding) || gettype($binding) === 'resource (closed)') {
                    return '<resource>';
                }
                return $binding;
            }, $log['bindings']);

            return [
                'raw_query' => $grammar->substituteBindingsIntoRawSql($log['query'], $bindings),
                'time' => $log['time'],
            ];
        }, $connection->getQueryLog());

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
     * Fetch the most recent snatched row for a torrent/user pair.
     *
     * Mirrors `get_snatch_info()`.
     *
     * @return array<string, mixed>|false
     */
    public static function snatchInfo(int|string $torrentId, int|string $userId): array|false
    {
        return \App\Repositories\TorrentRepository::getSnatchInfo($torrentId, $userId);
    }
}
