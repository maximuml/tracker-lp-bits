<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy search-suggestion helper extracted from `include/functions.php`.
 *
 * Backs `insert_suggest()`. The raw SQL is preserved because the
 * `$pre_escaped` flag controls whether the keyword has already been
 * escaped by the caller; moving to a prepared statement would change
 * the stored value for the `pre_escaped = true` legacy path.
 */
final class SearchSuggest
{
    /**
     * Insert a search keyword into the `suggest` table.
     *
     * Mirrors `insert_suggest($keyword, $userid, $pre_escaped)`.
     */
    public static function add(string $keyword, int|string $userId, bool $preEscaped = true): void
    {
        if (mb_strlen($keyword, 'UTF-8') < 2) {
            return;
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $value = $preEscaped ? "'" . $keyword . "'" : \App\Support\LegacyDb::escape($keyword);
        NexusDB::getInstance()->query(
            'INSERT INTO suggest(keywords, userid, adddate) VALUES ('
            . $value . ', ' . \App\Support\LegacyDb::escape($userId) . ', NOW())'
        );
    }
}
