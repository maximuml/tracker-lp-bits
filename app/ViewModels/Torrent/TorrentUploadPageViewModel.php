<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;
use App\ViewModels\ViewModel;

/**
 * ViewModel for the torrent upload page (GET /upload).
 *
 * Built in TorrentUploadController::form and handed to
 * `torrents.upload` (and its `components.torrents.upload` partial) as `vm`.
 * `altname_main`, `browsecatmode`, `max_torrent_size` and `$errors`
 * stay ambient via SharedViewVariables / ShareErrorsFromSession.
 */
final class TorrentUploadPageViewModel extends ViewModel
{
    /**
     * @param  list<array<string, mixed>>  $uploadErrorList
     * @param  array<int, array<string, mixed>>  $offerRows
     * @param  array<int, array<string, mixed>>  $cats
     * @param  array<int|string, mixed>  $posStates
     * @param  array<string, string>|string  $trackerUrl
     */
    public function __construct(
        public readonly array $uploadErrorList,
        public readonly bool $uploadFreely,
        public readonly bool $allowtorrents,
        public readonly array $offerRows,
        public readonly string $pageTitle,
        public readonly array $cats,
        public readonly array|string $trackerUrl,
        public readonly bool $torrentDirWritable,
        public readonly string $nameValue,
        public readonly bool $nameInvalid,
        public readonly string $priceLabel,
        public readonly bool $priceEnabled,
        public readonly mixed $priceValue,
        public readonly string $pricePlaceholder,
        public readonly bool $priceInvalid,
        public readonly string $priceHelp,
        public readonly string $descrContent,
        public readonly bool $descrInvalid,
        public readonly bool $enableTechnicalInfo,
        public readonly SafeHtml|string $taxonomySelectHtml,
        public readonly SafeHtml|string $customFieldsHtml,
        public readonly SafeHtml|string $hitAndRunHtml,
        public readonly SafeHtml|string $tagsHtml,
        public readonly bool $pickEnabled,
        public readonly array $posStates,
        public readonly string $posStateOld,
        public readonly bool $posStateInvalid,
        public readonly string $posStateUntil,
        public readonly bool $canBeAnonymous,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
