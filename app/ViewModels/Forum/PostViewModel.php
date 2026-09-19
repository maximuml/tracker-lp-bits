<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * One post row inside `x-forum.viewtopic`.
 *
 * Trusted-HTML fields (`by`, `body`, `signature`, `editedBy`, `ratio`,
 * `avatarImage`) are built by `UserDisplay`/`Format`/`Ratio` — the same
 * boundaries documented in ADR 0021/0022.
 */
final readonly class PostViewModel
{
    /**
     * @param  mixed  $addedRaw  raw timestamp/datetime for `<x-time>`
     * @param  mixed  $editedAtRaw  raw timestamp/datetime for `<x-time>`
     */
    public function __construct(
        public int $id,
        public int $number,
        public bool $isLast,
        public string $anchorUrl,
        public mixed $addedRaw,
        public SafeHtml $by,
        public string $authorToggleUrl,
        public string $authorToggleLabel,
        public SafeHtml $avatarImage,
        public string $classImage,
        public string $className,
        public int $postCount,
        public string $uploaded,
        public string $downloaded,
        public SafeHtml $ratio,
        public SafeHtml $body,
        public ?SafeHtml $signature,
        public ?SafeHtml $editedBy,
        public mixed $editedAtRaw,
        public bool $online,
        public int $posterId,
        public string $posterName,
        public bool $canQuote,
        public bool $canDelete,
        public bool $canEdit,
    ) {}
}
