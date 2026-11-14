<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the global search page (search/index.blade.php).
 *
 * Assembled by SearchController::search() from SearchPageRepository::dataForSearch()
 * plus the TorrentListViewFactory list VM.
 */
final class SearchPageViewModel extends ViewModel
{
    /**
     * @param  array{page?: int, pages?: int, href?: string, prevLabel?: string, nextLabel?: string, zeroBased?: bool}  $pager
     */
    public function __construct(
        public readonly string $search,
        public readonly bool $hasResults,
        public readonly array $pager,
        public readonly string $searchstr_ori,
        public readonly TorrentListViewModel $listVm,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
