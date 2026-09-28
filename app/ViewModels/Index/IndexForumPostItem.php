<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * One latest-forum-posts row on the index page.
 */
final readonly class IndexForumPostItem
{
    public function __construct(
        public int $tid,
        public int $pid,
        public string $subject,
        public int $forumid,
        public string $name,
        public int $views,
        public int $userpost,
        public string $added,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            tid: (int) ($row['tid'] ?? 0),
            pid: (int) ($row['pid'] ?? 0),
            subject: (string) ($row['subject'] ?? ''),
            forumid: (int) ($row['forumid'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            views: (int) ($row['views'] ?? 0),
            userpost: (int) ($row['userpost'] ?? 0),
            added: (string) ($row['added'] ?? ''),
        );
    }
}
