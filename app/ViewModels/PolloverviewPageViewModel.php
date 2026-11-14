<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the polloverview page (polloverview/index.blade.php).
 *
 * Assembled by PollController::polloverview(); covers detail and list modes; dead keys pagertop/pagerbottom dropped.
 */
final class PolloverviewPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $poll
     * @param  array<int, array<string, mixed>>  $pollOptions
     * @param  array<int, array<string, mixed>>  $answers
     * @param  array<string, mixed>  $pager
     * @param  array<int, array<string, mixed>>  $polls
     */
    public function __construct(
        public readonly string $mode,
        public readonly array $poll = [],
        public readonly SafeHtml|string $pollAdded = '',
        public readonly array $pollOptions = [],
        public readonly int $count = 0,
        public readonly array $answers = [],
        public readonly array $pager = [],
        public readonly array $polls = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
