<?php

declare(strict_types=1);

namespace App\ViewModels\Search;

/**
 * One labelled block of the category table — the category block itself
 * or one taxonomy block (sources, codecs, …). `rows` are pre-chunked to
 * the search box's cats-per-row setting so the template just iterates.
 */
final class SearchCategoryTableGroup
{
    /**
     * @param  list<list<SearchCategoryCell>>  $rows
     */
    public function __construct(
        public readonly string $label,
        public readonly array $rows,
    ) {}
}
