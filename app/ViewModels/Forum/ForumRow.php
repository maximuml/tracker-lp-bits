<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * One forum row on the index: name, counters, read/unread state,
 * last-post reference and moderators (ADR 0021).
 *
 * `moderators` is the comma-joined rich-username markup from
 * `Forum::moderatorsWithContext()`; `null` renders the
 * "apply now" contactstaff link in the component.
 */
final class ForumRow
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly int $topicCount,
        public readonly int $postCount,
        public readonly int $postsToday,
        public readonly bool $hasUnread,
        public readonly ?LastPostRef $lastPost,
        public readonly ?SafeHtml $moderators,
    ) {}
}
