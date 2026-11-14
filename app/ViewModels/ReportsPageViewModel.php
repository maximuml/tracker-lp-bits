<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the staff reports listing (reports/index.blade.php).
 *
 * Assembled by ModerationController::reports().
 */
final class ReportsPageViewModel extends ViewModel
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{page?: int, pages?: int, href?: string, prevLabel?: string, nextLabel?: string, zeroBased?: bool}  $pager
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $pager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
