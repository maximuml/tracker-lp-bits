<?php

declare(strict_types=1);

namespace App\ViewModels\Comment;

use App\Support\Html\SafeHtml;

/**
 * One rendered comment inside the shared comment table.
 */
final class CommentRow
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly SafeHtml $author,
        public readonly SafeHtml $addedTime,
        public readonly bool $showViewOriginal,
        public readonly SafeHtml $avatar,
        public readonly SafeHtml $text,
        public readonly ?SafeHtml $editedBy,
        public readonly ?SafeHtml $editedAt,
        public readonly bool $online,
        public readonly string $pmTitle,
        public readonly bool $canDelete,
        public readonly bool $canEdit,
    ) {}
}
