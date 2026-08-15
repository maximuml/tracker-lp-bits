<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy search-suggestion helper extracted from `include/functions.php`.
 *
 * Backs `insert_suggest()`.
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

        \App\Repositories\SearchRepository::addSuggestion($keyword, $userId, $preEscaped);
    }
}
