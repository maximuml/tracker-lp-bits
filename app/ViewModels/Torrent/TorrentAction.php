<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * One action link in the details "Action" row — sprite icon plus small
 * bold label, e.g. download/edit/reseed/report. The approval action
 * carries an inline SVG instead of a sprite (`iconHtml`) and the
 * `data-torrent_id` hook for the modal JS.
 */
final class TorrentAction
{
    public function __construct(
        public readonly string $url,
        public readonly string $title,
        public readonly string $iconClass,
        public readonly string $iconAlt,
        public readonly string $label,
        public readonly string $spanClass = 'small',
        public readonly ?string $spanId = null,
        public readonly ?int $dataTorrentId = null,
        public readonly ?SafeHtml $iconHtml = null,
        /** Render as a POST form instead of an anchor (mutating actions). */
        public readonly bool $isPost = false,
        /** @var array<string, string|int> */
        public readonly array $postFields = [],
    ) {}
}
