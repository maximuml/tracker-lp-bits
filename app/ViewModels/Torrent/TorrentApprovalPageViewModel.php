<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Models\Torrent;
use App\Models\TorrentDenyReason;
use App\Models\TorrentOperationLog;
use App\ViewModels\ViewModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ViewModel for the torrent approval page.
 *
 * Built in TorrentController::approvalPage and handed to
 * `torrent.approval` as `vm`.
 */
final class TorrentApprovalPageViewModel extends ViewModel
{
    /**
     * @param  Collection<int, TorrentDenyReason>  $denyReasons
     * @param  LengthAwarePaginator<int, TorrentOperationLog>  $logs
     * @param  array<int|string, mixed>  $approvalStatuses
     */
    public function __construct(
        public readonly Torrent $torrent,
        public readonly Collection $denyReasons,
        public readonly LengthAwarePaginator $logs,
        public readonly array $approvalStatuses,
        public readonly string $theme,
        public readonly string $fontSize,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
