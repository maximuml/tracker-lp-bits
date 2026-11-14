<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the bitbucketlog page (bitbucketlog/index.blade.php).
 *
 * Assembled by InfoController::bitbucketlog().
 */
final class BitbucketlogPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $pager
     */
    public function __construct(
        public readonly array $items,
        public readonly int $count,
        public readonly array $pager,
        public readonly bool $isModerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
