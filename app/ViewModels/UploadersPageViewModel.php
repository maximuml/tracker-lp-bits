<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the uploaders page (uploaders/index.blade.php).
 *
 * Assembled by BonusHistoryController::uploaders().
 */
final class UploadersPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $year = null,
        public readonly mixed $month = null,
        public readonly mixed $order = null,
        public readonly mixed $yearOptions = null,
        public readonly mixed $monthOptions = null,
        public readonly mixed $datefounded = null,
        public readonly mixed $timeStart = null,
        public readonly mixed $naText = null,
        public readonly mixed $rows = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['year', 'month', 'order', 'yearOptions', 'monthOptions', 'datefounded', 'timeStart', 'naText', 'rows'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
