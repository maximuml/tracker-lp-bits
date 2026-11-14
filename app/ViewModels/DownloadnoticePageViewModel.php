<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the downloadnotice page (downloadnotice/index.blade.php).
 *
 * Assembled by TorrentDownloadController::downloadnotice().
 */
final class DownloadnoticePageViewModel extends ViewModel
{
    public function __construct(
        public readonly int $torrentid,
        public readonly string $type,
        public readonly string|SafeHtml $title,
        public readonly SafeHtml $note,
        public readonly string $noticenexttime,
        public readonly bool $showrationotice,
        public readonly bool $showclientnotice,
        public readonly bool $forcecheck,
        public readonly string $tdattr,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
