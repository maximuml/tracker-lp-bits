<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * Prepared data for `torrents/_table.blade.php` — the Blade replacement
 * for `TorrentTable::render()` (Variant A, ADR 0014).
 *
 * @phpstan-type Column array{key: string, label: string, iconClass: string, iconTitle: string, sortUrl: ?string}
 */
final class TorrentListViewModel
{
    /**
     * @param  list<array{key: string, label: string, iconClass: string, iconTitle: string, sortUrl: ?string}>  $columns
     * @param  list<TorrentListRow>  $rows
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly bool $showComments,
        public readonly bool $canManage,
        public readonly bool $showPromotionNote,
        public readonly SafeHtml $lastCommentTooltips,
    ) {}
}
