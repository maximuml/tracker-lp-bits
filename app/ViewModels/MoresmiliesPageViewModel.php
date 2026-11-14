<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the moresmilies page (moresmilies/index.blade.php).
 *
 * Assembled by UtilityController.
 */
final class MoresmiliesPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string $form,
        public readonly string $text,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
