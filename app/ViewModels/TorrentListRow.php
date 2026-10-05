<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\Tag;
use App\Support\Html\SafeHtml;
use App\ViewModels\Torrent\CategoryIcon;
use App\ViewModels\Torrent\TorrentBadgeSet;
use App\ViewModels\Torrent\TorrentProgress;

/**
 * One prepared row of the modern torrents table (Variant A, ADR 0014).
 *
 * Cells are plain values rendered with {{ }} in the Blade table; the
 * only SafeHtml left is `uploaderName` — `UserDisplay::username()` is
 * inherently styled markup and stays behind the trusted boundary.
 */
final class TorrentListRow
{
    /**
     * @param  list<Tag>  $tags
     * @param  array{value: string, unit: string}  $size
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $rowClass,
        public readonly ?CategoryIcon $categoryIcon,
        public readonly ?CategoryIcon $secondIcon,
        public readonly ?string $coverSrc,
        public readonly int $stickyCount,
        public readonly string $stickyTitle,
        public readonly string $nameUrl,
        public readonly string $displayName,
        public readonly string $nameTitle,
        public readonly bool $isNew,
        public readonly bool $isBanned,
        public readonly TorrentBadgeSet $badges,
        public readonly array $tags,
        public readonly ?TorrentProgress $progress,
        public readonly bool $showDownload,
        public readonly string $downloadUrl,
        public readonly bool $showBookmark,
        public readonly ?string $waitText,
        public readonly ?string $waitClass,
        public readonly string $commentsUrl,
        public readonly int $comments,
        public readonly bool $commentIsNew,
        public readonly ?string $lastCommentTooltipId,
        public readonly int|string|\DateTimeInterface|null $added,
        public readonly string $addedDate,
        public readonly string $addedTime,
        public readonly array $size,
        public readonly ?string $seedersUrl,
        public readonly int $seeders,
        public readonly ?string $seedersClass,
        public readonly string $seedersZeroClass,
        public readonly ?string $leechersUrl,
        public readonly int $leechers,
        public readonly ?string $snatchedUrl,
        public readonly int $snatched,
        public readonly bool $uploaderAnonymous,
        public readonly bool $uploaderShowOwner,
        public readonly ?SafeHtml $uploaderName,
    ) {}
}
