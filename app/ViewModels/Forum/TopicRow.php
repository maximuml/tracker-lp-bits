<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * One topic row on the view-forum listing (ADR 0021).
 *
 * `subject` is `htmlspecialchars()`-escaped text with the fast-search
 * needle wrapped by `Format::highlight()` — already-safe markup.
 * `author`/`lastPoster` are `UserDisplay::username()` rich markup.
 * `state` is one of read|unread|locked|lockednew and maps to the sprite
 * class in the component. `visiblePages` holds the multipage quick links
 * (1-based display numbers; the URL `page=` stays 0-based).
 * `jumpToPostId` targets the first-unread-post anchor link.
 */
final class TopicRow
{
    /**
     * @param  list<int|string>  $visiblePages  1-based display numbers, '…' for the gap
     */
    public function __construct(
        public readonly int $id,
        public readonly int $forumId,
        public readonly SafeHtml $subject,
        public readonly int $hlcolor,
        public readonly bool $sticky,
        public readonly string $state,
        public readonly array $visiblePages,
        public readonly ?int $jumpToPostId,
        public readonly ?string $tooltipId,
        public readonly SafeHtml $author,
        public readonly string $firstAdded,
        public readonly bool $firstAddedRecent,
        public readonly int $replies,
        public readonly int $views,
        public readonly string $lastPostAt,
        public readonly SafeHtml $lastPoster,
    ) {}

    /**
     * Sprite class + accessible label for the topic-state icon.
     *
     * @return array{0: string, 1: string, 2: string} class, alt, title
     */
    public function stateIcon(): array
    {
        return match ($this->state) {
            'unread' => ['unlockednew', 'unread', __('legacy/forums.title_unread')],
            'locked' => ['locked', 'locked', __('legacy/forums.title_locked')],
            'lockednew' => ['lockednew', 'lockednew', __('legacy/forums.title_locked_new')],
            default => ['unlocked', 'read', __('legacy/forums.title_read')],
        };
    }
}
