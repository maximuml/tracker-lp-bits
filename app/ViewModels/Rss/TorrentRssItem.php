<?php

declare(strict_types=1);

namespace App\ViewModels\Rss;

use Illuminate\Support\HtmlString;

/**
 * One <item> of the torrents RSS feed — HtmlString fields are already
 * CDATA-safe from the controller's $cdata() escaping.
 */
final class TorrentRssItem
{
    public function __construct(
        public readonly HtmlString $title,
        public readonly string $url,
        public readonly HtmlString $content,
        public readonly string $author,
        public readonly int $categoryId,
        public readonly string $categoryName,
        public readonly HtmlString $commentsUrl,
        public readonly string $downloadUrl,
        public readonly int $size,
        public readonly string $guid,
        public readonly string $pubDate,
    ) {}
}
