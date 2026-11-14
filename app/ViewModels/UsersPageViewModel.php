<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the users page (users/index.blade.php).
 *
 * Assembled by UserAdminController::users(); dead keys (class/country/letter/pagerParam/pagertop/pagerbottom) dropped at the controller.
 */
final class UsersPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $classOptions
     * @param  array<int, array<string, mixed>>  $countryOptions
     * @param  array<int, array<string, mixed>>  $letterItems
     * @param  array<string, mixed>  $pager
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly string $search,
        public readonly array $classOptions,
        public readonly array $countryOptions,
        public readonly array $letterItems,
        public readonly array $pager,
        public readonly array $rows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
