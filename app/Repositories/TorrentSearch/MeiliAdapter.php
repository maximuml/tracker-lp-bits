<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Repositories\MeiliSearchRepository;

/**
 * Adapt the search parameters for MeiliSearch execution.
 *
 * Wraps MeiliSearchRepository::search. Throws on failure so the caller
 * (TorrentSearchRepository) can fall back to the SQL path.
 *
 * Extracted from TorrentSearchRepository (W2-05).
 */
final class MeiliAdapter
{
    /**
     * Execute a MeiliSearch query.
     *
     * @param  array<string, mixed>  $searchParams
     * @return mixed Result with `total` and `list` keys (mirrors MeiliSearchRepository::search)
     */
    public function search(array $searchParams, mixed $userId): mixed
    {
        return app(MeiliSearchRepository::class)->search($searchParams, $userId);
    }
}
