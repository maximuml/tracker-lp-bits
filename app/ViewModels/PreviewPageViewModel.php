<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the preview page (preview/index.blade.php).
 *
 * Assembled by UtilityController.
 */
final class PreviewPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string $body,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
