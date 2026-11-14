<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the reset page (reset/index.blade.php).
 *
 * Assembled by UserAdminController::resetPage().
 */
final class ResetPageViewModel extends ViewModel
{
    public function __construct(
        public readonly bool $success,
        public readonly SafeHtml $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
