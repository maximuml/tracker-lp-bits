<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

/**
 * Parameter object bundling the search-pipeline components of
 * TorrentSearchRepository — keeps its constructor within the
 * RepositorySizeTest dependency cap.
 */
final readonly class SearchEngine
{
    public function __construct(
        public QueryBuilder $queryBuilder,
        public SortingBuilder $sortingBuilder,
        public FilterParser $filterParser,
        public MeiliAdapter $meiliAdapter,
        public SqlFallback $sqlFallback,
    ) {}
}
