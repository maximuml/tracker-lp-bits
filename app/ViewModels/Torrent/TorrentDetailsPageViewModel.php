<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Support\Html\SafeHtml;
use App\ViewModels\ViewModel;

/**
 * ViewModel for the torrent details page (GET /details?id=N).
 *
 * Assembled in TorrentDetailsController::show / buildDetailsViewData
 * and handed to `torrent.details` as `vm`. The inner `$details`
 * payload is already the typed TorrentDetailsViewModel.
 */
final class TorrentDetailsPageViewModel extends ViewModel
{
    /**
     * @param  array<int|string, mixed>  $torrentRow
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int, int>  $tagIds
     * @param  array<string, mixed>  $requestFlags
     * @param  array<int, string>  $screenshots
     * @param  array<string, mixed>  $commentPager
     */
    public function __construct(
        public readonly int $id,
        public readonly int $torrentId,
        public readonly array $torrentRow,
        public readonly User $user,
        public readonly array $currentUser,
        public readonly string $headTitle,
        public readonly array $tagIds,
        public readonly ?TorrentOperationLog $denyLog,
        public readonly bool $hasBuy,
        public readonly array $requestFlags,
        public readonly bool $torrentBookmarked,
        public readonly TorrentDetailsViewModel $details,
        public readonly SafeHtml $tagHtml,
        public readonly string $downloadUrl,
        public readonly mixed $customFieldsHtml,
        public readonly mixed $technicalInfoResult,
        public readonly string $descrRaw,
        public readonly array $screenshots,
        public readonly bool $showDescription,
        public readonly string $torrentNamePrefix,
        public readonly int $commentCount,
        public readonly SafeHtml $commentPagerTop,
        public readonly SafeHtml $commentPagerBottom,
        public readonly array $commentPager,
        public readonly bool $commentsEnabled,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
