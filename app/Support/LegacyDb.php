<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Generic legacy DB helpers extracted from `include/functions.php`.
 *
 * Backs the `get_row_sum()` and `get_single_value()` procedural wrappers.
 *
 * These methods deliberately accept raw `$suffix` SQL because the legacy
 * callers pass unparameterised `WHERE`/`GROUP BY` fragments. The support
 * class only centralises the query shape and result extraction; the
 * wrappers remain responsible for any legacy sanitisation they already did.
 */
final class LegacyDb
{
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
}
