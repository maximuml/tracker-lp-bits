<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\TorrentListingRepository;

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

        self::torrentListingRepository()->recordSuggestKeyword([
            'keywords' => $preEscaped ? stripslashes($keyword) : $keyword,
            'userid' => $userId,
            'adddate' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function torrentListingRepository(): TorrentListingRepository
    {
        return app(TorrentListingRepository::class);
    }
}
