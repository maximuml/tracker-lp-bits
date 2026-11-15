<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the attendance page (attendance/index.blade.php).
 *
 * Wraps AttendancePageService::build() output + bonusLines; any extra keys the service adds are kept.
 */
final class AttendancePageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $captchaHtml = null,
        public readonly mixed $hasAttendedToday = null,
        public readonly mixed $headerLeft = null,
        public readonly mixed $headerRight = null,
        public readonly mixed $bonusLines = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['captchaHtml', 'hasAttendedToday', 'headerLeft', 'headerRight', 'bonusLines'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
