<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
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
    public function __construct(
        private readonly MeiliSearchRepositoryInterface $meiliSearchRepository,
    ) {}

    /**
     * Execute a MeiliSearch query.
     *
     * @param  array<string, mixed>  $searchParams
     * @return mixed Result with `total` and `list` keys (mirrors MeiliSearchRepository::search)
     */
    public function search(array $searchParams, mixed $userId): mixed
    {
        return $this->meiliSearchRepository->search($searchParams, $userId);
    }
}
