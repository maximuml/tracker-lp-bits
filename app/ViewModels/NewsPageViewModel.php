<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the news page (news/index.blade.php).
 *
 * Assembled by NewsController::news(); covers add and edit modes.
 */
final class NewsPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $mode = null,
        public readonly mixed $newsid = null,
        public readonly mixed $body = null,
        public readonly mixed $subject = null,
        public readonly mixed $notify = null,
        public readonly mixed $returnto = null,
        public readonly mixed $title = null,
        public readonly mixed $composeTitle = null,
        public readonly mixed $checked = null,
        public readonly mixed $actionUrl = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['mode', 'newsid', 'body', 'subject', 'notify', 'returnto', 'title', 'composeTitle', 'checked', 'actionUrl'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
