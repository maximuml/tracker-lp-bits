<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the staff roster page (staff/index.blade.php).
 *
 * Assembled by StaffPageController::staff(); the component include
 * `components.staff.staff` consumes the four row groups + siteName.
 */
final class StaffPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $supportRows
     * @param  array<int, array<string, mixed>>  $forumModRows
     * @param  array<int, array<string, mixed>>  $staffRows
     * @param  array<int, array<string, mixed>>  $vipRows
     */
    public function __construct(
        public readonly array $supportRows,
        public readonly array $forumModRows,
        public readonly array $staffRows,
        public readonly array $vipRows,
        public readonly string $siteName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
