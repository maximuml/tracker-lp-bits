<?php

declare(strict_types=1);

namespace App\ViewModels\Search;

/**
 * The whole category/taxonomy checkbox grid for a search box —
 * typed replacement for the markup `SearchBox::buildCategoryTable()`
 * used to concatenate. Rendered by `x-search-category-table`.
 */
final class SearchCategoryTableViewModel
{
    /**
     * @param  list<SearchCategoryTableGroup>  $groups
     */
    public function __construct(
        public readonly ?string $sectionName,
        public readonly array $groups,
    ) {}
}
