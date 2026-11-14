<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the thanks page (thanks/index.blade.php).
 *
 * Assembled by TorrentBookmarkController::thanks().
 */
final class ThanksPageViewModel extends ViewModel
{
    public function __construct(
        public readonly int $torrentid,
        public readonly string $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
