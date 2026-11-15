<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the tags page (tags/index.blade.php).
 *
 * Assembled by SearchController::tags().
 */
final class TagsPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $tagItems
     */
    public function __construct(
        public readonly string $test,
        public readonly string $siteName,
        public readonly array $tagItems,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
