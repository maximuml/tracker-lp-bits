<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the ok page (ok/index.blade.php).
 *
 * Assembled by UtilityController.
 */
final class OkPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $CURUSER
     */
    public function __construct(
        public readonly string $type,
        public readonly string $email,
        public readonly string $title,
        public readonly string $siteName,
        public readonly array $CURUSER,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
