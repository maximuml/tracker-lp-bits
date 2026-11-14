<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the location page (location/index.blade.php).
 *
 * Assembled by AdminToolsController::location(); covers both list and edit modes.
 */
final class LocationPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $editRow
     * @param  array<string, mixed>  $pager
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $actionUrl,
        public readonly array $editRow = [],
        public readonly bool $success = false,
        public readonly string $error = '',
        public readonly SafeHtml|string $message = '',
        public readonly string $rangeStartIp = '',
        public readonly string $rangeEndIp = '',
        public readonly bool $hasRangeFilter = false,
        public readonly array $pager = [],
        public readonly array $rows = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
