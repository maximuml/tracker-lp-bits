<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * One row of the forum search results (ADR 0021): the post id, the
 * highlighted topic link, the parent forum and the poster stamp.
 *
 * `subject` is `htmlspecialchars()`-escaped text with the keyword needle
 * wrapped by `Format::highlight()`; `poster` is `UserDisplay::username()`
 * rich markup — the two documented SafeHtml fields.
 */
final class SearchResultRow
{
    public function __construct(
        public readonly int $postId,
        public readonly int $topicId,
        public readonly SafeHtml $subject,
        public readonly int $hlcolor,
        public readonly int $forumId,
        public readonly string $forumName,
        public readonly string $added,
        public readonly SafeHtml $poster,
    ) {}
}
