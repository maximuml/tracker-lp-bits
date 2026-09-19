<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\View\Components\Pagination;

/**
 * View model for the forum search section (ADR 0021): the keyword form
 * and, once `searched`, the result rows with pagination.
 *
 * `keywords` is the raw query string — the component escapes it via
 * `{{ }}` for the input value and `rawurlencode()` for links.
 * `page` is the 0-based legacy `page=` value, same convention as
 * {@see TopicListViewModel}.
 */
final class ForumSearchViewModel
{
    /**
     * @param  list<SearchResultRow>  $results
     */
    public function __construct(
        public readonly string $keywords,
        public readonly bool $searched,
        public readonly int $hits,
        public readonly array $results,
        public readonly int $page,
        public readonly int $pages,
        public readonly string $imageUrl,
    ) {}

    /**
     * Pager base URL — page number is appended by the component.
     */
    public function pagerHref(): string
    {
        return 'forums.php?action=search&keywords='.rawurlencode($this->keywords).'&';
    }

    /**
     * 1-based display window for `x-forum.pager` (maps back to the
     * 0-based `page=` URLs inside the component).
     *
     * @return list<int|string>
     */
    public function pagerItems(): array
    {
        return Pagination::window($this->page + 1, $this->pages);
    }

    /**
     * View-topic link for a result row (0-based `page=p{id}` anchor).
     */
    public function resultUrl(int $topicId, int $postId): string
    {
        return '?action=viewtopic&topicid='.$topicId.'&highlight='.rawurlencode($this->keywords).'&page=p'.$postId.'#pid'.$postId;
    }
}
