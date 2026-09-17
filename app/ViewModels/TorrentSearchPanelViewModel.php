<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * Prepared data for the modern torrents search panel — the Blade
 * replacement for `SearchBox::buildCategoryTable()` (Variant A, ADR 0014).
 *
 * Cells are pre-chunked into visual rows so the template only loops.
 * `selectAll` cells carry the checkbox-group prefix for the legacy
 * SetChecked JS hook (data-setchecked / data-checkall / data-uncheckall).
 *
 * @phpstan-type PanelCell array{selectAll: true, checkPrefix: string}|array{selectAll: false, checkPrefix: string, id: int, name: string, checked: bool, checkboxName: string, href: string, iconClass: string, iconStyle: string}
 * @phpstan-type TaxonomySection array{label: string, rows: list<list<PanelCell>>}
 */
final class TorrentSearchPanelViewModel
{
    /**
     * @param  list<list<PanelCell>>  $categoryRows
     * @param  list<TaxonomySection>  $taxonomySections
     * @param  list<string>  $hotSearches
     * @param  array<string, string>  $searchModes
     * @param  array<int, string>  $promotionOptions
     */
    public function __construct(
        public readonly array $categoryRows,
        public readonly array $taxonomySections,
        public readonly array $hotSearches,
        public readonly string $categoryLabel,
        public readonly int $catPadding,
        public readonly array $searchModes,
        public readonly array $promotionOptions,
        public readonly string $selectAllLabel,
        public readonly string $unselectAllLabel,
    ) {}
}
