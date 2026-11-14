<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the delete page (delete/index.blade.php).
 *
 * Assembled by TorrentDeleteController::delete(); both props optional — the view tolerates missing message/returnto on the non-success renders.
 */
final class DeletePageViewModel extends ViewModel
{
    public function __construct(
        public readonly ?string $returnto = null,
        public readonly ?string $message = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
