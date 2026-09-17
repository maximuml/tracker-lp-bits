<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * One prepared row of the modern torrents table (Variant A, ADR 0014).
 *
 * Scalar cells are plain values rendered with {{ }} in the Blade table;
 * compound fragments produced by shared helpers (category icons,
 * promotion badges, tag spans, progress bar, usernames) arrive as
 * SafeHtml so the template never calls fromTrustedHtml() itself.
 */
final class TorrentListRow
{
    public function __construct(
        public readonly int $id,
        public readonly SafeHtml $rowAttrs,
        public readonly SafeHtml $categoryCell,
        public readonly ?string $coverSrc,
        public readonly int $stickyCount,
        public readonly string $stickyTitle,
        public readonly string $nameUrl,
        public readonly string $displayName,
        public readonly string $nameTitle,
        public readonly bool $isNew,
        public readonly bool $isBanned,
        public readonly SafeHtml $badges,
        public readonly SafeHtml $tags,
        public readonly SafeHtml $progressBar,
        public readonly bool $showDownload,
        public readonly string $downloadUrl,
        public readonly bool $showBookmark,
        public readonly string $bookmarkElementId,
        public readonly int $bookmarkCounter,
        public readonly SafeHtml $bookmarkMarkup,
        public readonly ?string $waitText,
        public readonly ?string $waitColor,
        public readonly string $commentsUrl,
        public readonly int $comments,
        public readonly bool $commentIsNew,
        public readonly ?string $lastCommentTooltipId,
        public readonly SafeHtml $time,
        public readonly SafeHtml $size,
        public readonly ?string $seedersUrl,
        public readonly int $seeders,
        public readonly ?string $seedersColor,
        public readonly string $seedersZeroClass,
        public readonly ?string $leechersUrl,
        public readonly int $leechers,
        public readonly ?string $snatchedUrl,
        public readonly int $snatched,
        public readonly bool $uploaderAnonymous,
        public readonly bool $uploaderShowOwner,
        public readonly ?SafeHtml $uploaderName,
        public readonly ?string $staffDeleteUrl,
        public readonly ?string $staffEditUrl,
    ) {}
}
