<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

/**
 * View model for the view-unread-posts section (ADR 0021): topics with
 * posts newer than the user's last read marker, plus the catch-up and
 * show-more actions.
 *
 * `moreBeforePostId` feeds the `beforepostid` continuation link when the
 * result list was truncated by the per-request cap.
 */
final class UnreadTopicsViewModel
{
    /**
     * @param  list<UnreadTopicRow>  $topics
     */
    public function __construct(
        public readonly string $siteName,
        public readonly array $topics,
        public readonly ?int $moreBeforePostId,
    ) {}
}
