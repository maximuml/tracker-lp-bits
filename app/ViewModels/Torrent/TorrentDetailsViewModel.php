<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * Typed payload for the torrent details table — replaces the dozen
 * `*Html` strings assembled by `TorrentDetailsController`.
 *
 * Boundaries that stay SafeHtml: `bookmark`, `tags`, `customFields`,
 * `technicalInfo`, `descr` (BBCode output), `quickReply`, comments.
 */
final class TorrentDetailsViewModel
{
    /**
     * @param  list<TaxonomyEntry>  $taxonomy
     * @param  list<TorrentAction>  $actions
     */
    public function __construct(
        public readonly TorrentTitleLine $title,
        public readonly OwnerAttribution $owner,
        public readonly array $taxonomy,
        public readonly array $actions,
        public readonly TorrentInfoRow $info,
        public readonly HotMeterRow $hotMeter,
        public readonly PeersRow $peers,
        public readonly ?DenyBanner $denyBanner,
        public readonly string $uploadTimePrefix,
        public readonly SafeHtml $uploadTime,
        public readonly string $showOrHideTitle,
        public readonly bool $downloadAllowed,
        public readonly string $saveAs,
        public readonly SafeHtml $bookmark,
    ) {}
}
