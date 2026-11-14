<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the shoutbox_history page (shoutbox_history/index.blade.php).
 *
 * Assembled by ShoutboxController::history().
 */
final class ShoutboxHistoryPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $items = null,
        public readonly mixed $page = null,
        public readonly mixed $totalPages = null,
        public readonly mixed $paginationBase = null,
        public readonly mixed $filters = null,
        public readonly mixed $csrfToken = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['items', 'page', 'totalPages', 'paginationBase', 'filters', 'csrfToken'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
