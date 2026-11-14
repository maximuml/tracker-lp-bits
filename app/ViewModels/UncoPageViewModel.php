<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the unco page (unco/index.blade.php).
 *
 * Assembled by UserAdminController::unco().
 */
final class UncoPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly ?int $status,
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
