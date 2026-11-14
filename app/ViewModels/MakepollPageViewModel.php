<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the makepoll page (makepoll/index.blade.php).
 *
 * Assembled by PollController::makepoll().
 */
final class MakepollPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $poll
     * @param  array<string, string>|null  $pollAge
     */
    public function __construct(
        public readonly array $poll,
        public readonly int $pollid,
        public readonly string $returnto,
        public readonly ?array $pollAge,
        public readonly string|SafeHtml $title,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
