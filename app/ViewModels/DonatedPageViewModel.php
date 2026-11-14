<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the donated page (donated/index.blade.php).
 *
 * Assembled by InfoController::donatedPage().
 */
final class DonatedPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string $error,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
