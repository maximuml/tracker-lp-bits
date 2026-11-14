<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the viewsnatches page (viewsnatches/index.blade.php).
 *
 * Assembled by TorrentAjaxController::viewsnatches() from TorrentAjaxRepository::snatchList() output; dead keys pagertop/pager dropped, snatchedRows is replaced by rows.
 */
final class ViewsnatchesPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly int $id,
        public readonly string $torrentName,
        public readonly int $count,
        public readonly array $rows = [],
        public readonly string|SafeHtml $pagerbottom = '',
        public readonly bool $canViewConfidential = false,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['id', 'torrentName', 'count', 'pagerbottom', 'rows', 'canViewConfidential'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
