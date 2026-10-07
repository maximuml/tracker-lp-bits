<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Read-side helpers over the current connection's query log, used for
 * diagnostics (logging the last raw query, footer query counts).
 */
final class QueryLog
{
    /**
     * The last executed query rendered with bindings substituted
     * ('json' → JSON string, anything else → the raw array). Empty
     * string / empty result when no query ran yet.
     */
    public static function last(string $format = 'json'): mixed
    {
        $queries = self::queries();
        if ($queries === []) {
            return '';
        }

        $last = last($queries);
        if ($format === 'json') {
            return Json::encode($last);
        }

        return $last;
    }

    /** @return list<array{raw_query: string, time: mixed}> */
    public static function all(): array
    {
        return self::queries();
    }

    public static function count(): int
    {
        return count(self::connection()->getQueryLog());
    }

    /** @return list<array{raw_query: string, time: mixed}> */
    private static function queries(): array
    {
        $connection = self::connection();
        $grammar = $connection->getQueryGrammar();

        return array_values(array_map(static function (array $log) use ($grammar) {
            $bindings = array_map(static function ($binding) {
                if (is_string($binding) && preg_match('//u', $binding) === false) {
                    return '[binary:'.bin2hex($binding).']';
                }
                if (is_resource($binding) || gettype($binding) === 'resource (closed)') {
                    return '[resource]';
                }

                return $binding;
            }, $log['bindings']);

            return [
                'raw_query' => $grammar->substituteBindingsIntoRawSql($log['query'], $bindings),
                'time' => $log['time'],
            ];
        }, $connection->getQueryLog()));
    }

    private static function connection(): Connection
    {
        return DB::connection(Config::get('nexus.database.default', null));
    }
}
