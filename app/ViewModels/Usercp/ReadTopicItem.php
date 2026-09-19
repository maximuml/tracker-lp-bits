<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

use App\Support\Html\SafeHtml;

/**
 * One row of the "recently read topics" table in the usercp home
 * section. `author`/`lastPostUsername` come from
 * `UserDisplay::username()` (rich username markup, already trusted).
 * `lastPostAdded` is a raw datetime — the view renders `<x-time>`.
 */
final readonly class ReadTopicItem
{
    public function __construct(
        public int $id,
        public string $subject,
        public string $views,
        public int $replies,
        public SafeHtml $author,
        public ?string $lastPostAdded,
        public SafeHtml $lastPostUsername,
    ) {}
}
