<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;
use App\ViewModels\ViewModel;

/**
 * ViewModel for the torrent edit page (GET /edit?id=N).
 *
 * Built in TorrentEditController and handed to `torrent.edit` as `vm`.
 */
final class TorrentEditPageViewModel extends ViewModel
{
    /**
     * @param  array<int|string, mixed>  $torrentRow
     * @param  array<int|string, mixed>|null  $currentUser
     * @param  array<int, int>  $tagIds
     * @param  array<int, array<string, mixed>>  $cats
     * @param  array<string, mixed>|null  $priceRow
     */
    public function __construct(
        public readonly int $torrentId,
        public readonly array $torrentRow,
        public readonly ?array $currentUser,
        public readonly string $headTitle,
        public readonly array $tagIds,
        public readonly array $cats,
        public readonly string $returnto,
        public readonly string $requestUri,
        public readonly SafeHtml|string $taxonomySelect,
        public readonly SafeHtml|string $tagCheckbox,
        public readonly SafeHtml|string $customFieldsHtml,
        public readonly SafeHtml|string $hitAndRunHtml,
        public readonly bool $canEdit,
        public readonly ?array $priceRow,
        public readonly int $sectionMode,
        public readonly bool $showVisibleCheck,
        public readonly bool $visibleChecked,
        public readonly bool $showAnonymousCheck,
        public readonly bool $anonymousChecked,
        public readonly ?TorrentEditPickViewModel $pick,
        public readonly bool $showDeleteForm,
        public readonly string $descrContent,
        public readonly bool $technicalInfoEnabled,
        public readonly string $modeClass,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
