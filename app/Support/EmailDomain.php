<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy email-domain list lookups from `include/functions.php`.
 *
 * Backs the `allowedemails()` helper and the DB half of the
 * `EmailAllowed()` / `EmailBanned()` checks. The actual regex
 * matching lives in {@see Email} so this class stays a thin
 * data-access shim.
 */
final class EmailDomain
{
    /**
     * Return the raw `value` column from the `allowedemails` table,
     * or an empty string if no row exists.
     */
    public static function allowed(): string
    {
        $row = NexusDB::table('allowedemails')->first();

        return (string) ($row->value ?? '');
    }

    /**
     * Return the raw `value` column from the `bannedemails` table,
     * or an empty string if no row exists.
     */
    public static function banned(): string
    {
        $row = NexusDB::table('bannedemails')->first();

        return (string) ($row->value ?? '');
    }
}
