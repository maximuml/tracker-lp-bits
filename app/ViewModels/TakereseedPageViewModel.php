<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the takereseed page (takereseed/index.blade.php).
 *
 * Assembled by TorrentMaintenanceController::takeReseed().
 */
final class TakereseedPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string|SafeHtml $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
