<?php

declare(strict_types=1);

namespace App\ViewModels\Rss;

use App\ViewModels\ViewModel;
use Illuminate\Support\HtmlString;

/**
 * ViewModel for the torrents RSS feed channel (rss/torrents.blade.php).
 *
 * Assembled by TorrentRssController when rebuilding the cached feed.
 */
final class TorrentRssViewModel extends ViewModel
{
    /**
     * @param  list<TorrentRssItem>  $items
     */
    public function __construct(
        public readonly string $channelTitle,
        public readonly HtmlString $baseUrl,
        public readonly HtmlString $description,
        public readonly string $copyright,
        public readonly string $siteEmail,
        public readonly string $siteName,
        public readonly string $pubDate,
        public readonly string $projectName,
        public readonly string $httpHost,
        public readonly array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
