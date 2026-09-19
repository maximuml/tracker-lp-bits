<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * One row of the unread-topics list (ADR 0021): the topic link (with an
 * optional jump-to-first-unread anchor) and its parent forum link.
 *
 * `subject` is `htmlspecialchars()`-escaped text — SafeHtml only marks
 * that escaping already happened, no markup is injected by the service.
 */
final class UnreadTopicRow
{
    public function __construct(
        public readonly int $topicId,
        public readonly SafeHtml $subject,
        public readonly int $hlcolor,
        public readonly ?int $jumpToPostId,
        public readonly int $forumId,
        public readonly string $forumName,
    ) {}
}
