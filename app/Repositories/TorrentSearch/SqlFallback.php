<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Repositories\TorrentListingRepository;

/**
 * SQL fallback for torrent listing when MeiliSearch is unavailable.
 *
 * Wraps TorrentListingRepository count/list operations so the
 * TorrentSearchRepository facade does not depend on it directly.
 *
 * Extracted from TorrentSearchRepository (W2-05).
 */
final class SqlFallback
{
    /**
     * Count torrents matching the listing options.
     *
     * @param  array<int|string, mixed>  $listingOptions
     */
    public function getCount(array $listingOptions): int
    {
        return app(TorrentListingRepository::class)->getCount($listingOptions);
    }

    /**
     * Fetch a page of torrents matching the listing options.
     *
     * @param  array<int|string, mixed>  $listingOptions
     * @return array<int|string, mixed>
     */
    public function getList(array $listingOptions): array
    {
        return app(TorrentListingRepository::class)->getList($listingOptions);
    }
}
