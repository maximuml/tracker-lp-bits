<?php

declare(strict_types=1);

namespace App\ViewModels\Comment;

/**
 * The legacy comment table (commenttable()): outer frame plus one
 * CommentRow per comment record.
 *
 * @phpstan-type CommentRowArray array<string, mixed>
 */
final class CommentTableViewModel
{
    /**
     * @param  list<CommentRow>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly string $type,
        public readonly int|string $parentId,
        public readonly int $contentWidth,
        public readonly string $reportTitle,
        public readonly string $replyTitle,
    ) {}
}
