<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * Prepared data for `torrents/_table.blade.php` — the Blade replacement
 * for `TorrentTable::render()` (Variant A, ADR 0014).
 *
 * @phpstan-type Column array{key: string, label: string, iconClass: string, iconTitle: string, sortUrl: ?string}
 * @phpstan-type Tooltip array{id: string, content: SafeHtml}
 */
final class TorrentListViewModel
{
    /**
     * @param  list<Column>  $columns
     * @param  list<TorrentListRow>  $rows
     * @param  list<Tooltip>  $lastCommentTooltips  domTT tooltip bodies,
     *                                              rendered inside a hidden container by the template.
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly bool $showComments,
        public readonly bool $canManage,
        public readonly bool $showPromotionNote,
        public readonly array $lastCommentTooltips,
    ) {}
}
