<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * One row of the per-user torrent history table rendered by
 * `getusertorrentlistajax` (uploaded/seeding/leeching/completed/
 * incomplete modes). Replaces the concatenated markup built by
 * `TorrentAjaxController::torrentListTable()`.
 *
 * Sizes are `Format::sizeParts()` pairs so the template owns the `<br />`
 * separator; `completedAt` is a raw timestamp rendered via `<x-time>`.
 */
final class UserTorrentRow
{
    /**
     * @param  array{value: string, unit: string}  $size
     * @param  array{value: string, unit: string}  $uploaded
     * @param  array{value: string, unit: string}  $downloaded
     * @param  list<string>  $clientIps
     */
    public function __construct(
        public readonly ?string $rowClass,
        public readonly ?CategoryIcon $categoryIcon,
        public readonly string $nameUrl,
        public readonly string $nameTitle,
        public readonly string $displayName,
        public readonly bool $isBanned,
        public readonly TorrentBadgeSet $badges,
        public readonly string $addedDate,
        public readonly string $addedTime,
        public readonly array $size,
        public readonly int $seeders,
        public readonly int $leechers,
        public readonly array $uploaded,
        public readonly array $downloaded,
        public readonly string $ratioText,
        public readonly ?string $ratioClass,
        public readonly string $seedTime,
        public readonly string $leechTime,
        public readonly mixed $completedAt,
        public readonly string $anonymous,
        public readonly string $clientAgent,
        public readonly string $clientPort,
        public readonly array $clientIps,
    ) {}
}
