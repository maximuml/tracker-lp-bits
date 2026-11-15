<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the getrss page (getrss/index.blade.php).
 *
 * Wraps RssController::getrssData() output.
 */
final class GetrssPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $browsecatmode = null,
        public readonly mixed $brsectiontype = null,
        public readonly mixed $showsubcat = null,
        public readonly mixed $showsource = null,
        public readonly mixed $showmedium = null,
        public readonly mixed $showcodec = null,
        public readonly mixed $showstandard = null,
        public readonly mixed $showprocessing = null,
        public readonly mixed $showaudiocodec = null,
        public readonly mixed $catsperrow = null,
        public readonly mixed $catpadding = null,
        public readonly mixed $brcats = null,
        public readonly mixed $categoriesTable = null,
        public readonly mixed $paidTorrentEnabled = null,
        public readonly mixed $allowed_showrows = null,
        public readonly mixed $stickyTypes = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['browsecatmode', 'brsectiontype', 'showsubcat', 'showsource', 'showmedium', 'showcodec', 'showstandard', 'showprocessing', 'showaudiocodec', 'catsperrow', 'catpadding', 'brcats', 'categoriesTable', 'paidTorrentEnabled', 'allowed_showrows', 'stickyTypes'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
