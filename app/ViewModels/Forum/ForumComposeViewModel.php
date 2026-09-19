<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * The forum compose form (newtopic / reply / quotepost / editpost).
 *
 * `titleHtml` carries the already-linked heading ("Reply to topic <a>…"),
 * `body`/`subject` are raw text — the template escapes them once for the
 * textarea/value contexts.
 */
final readonly class ForumComposeViewModel
{
    public function __construct(
        public SafeHtml $titleHtml,
        public int $hiddenId,
        public string $hiddenType,
        public ?int $postid,
        public bool $hasSubject,
        public string $subject,
        public string $body,
        public int $maxSubjectLength,
    ) {}

    public function frameCaption(): string
    {
        $typeKey = match ($this->hiddenType) {
            'reply' => 'text_reply',
            'quote' => 'text_quote',
            'edit' => 'text_edit',
            default => 'text_new',
        };

        return (string) __('legacy/functions.'.$typeKey);
    }
}
