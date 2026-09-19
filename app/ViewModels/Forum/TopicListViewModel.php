<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;
use App\View\Components\Pagination;

/**
 * View model for the view-forum section (ADR 0021): the topic list of a
 * single forum with sort links, fast-search form and pagination.
 *
 * `page` is the 0-based legacy `page=` query value — `x-forum.pager`
 * renders the 1-based display while keeping the legacy URL semantics.
 * `tooltips` are the hidden domTT containers for last-post previews;
 * `content` is `Format::formatComment()` output (BBCode-rendered, the
 * same justification as the post body).
 */
final class TopicListViewModel
{
    /**
     * @param  list<TopicRow>  $topics
     * @param  list<array{id: string, content: SafeHtml}>  $tooltips
     */
    public function __construct(
        public readonly string $siteName,
        public readonly int $forumId,
        public readonly string $forumName,
        public readonly bool $mayPost,
        public readonly ?SafeHtml $moderators,
        public readonly string $search,
        public readonly string $sort,
        public readonly array $topics,
        public readonly int $page,
        public readonly int $pages,
        public readonly array $tooltips,
    ) {}

    /**
     * Extra query fragment carried by sort and pager links
     * (`&search=…` when a fast-search filter is active).
     */
    public function addParam(): string
    {
        return $this->search === '' ? '' : '&search='.rawurlencode($this->search);
    }

    /**
     * Pager base URL — page number is appended by the component.
     */
    public function pagerHref(): string
    {
        return '?action=viewforum&forumid='.$this->forumId.$this->addParam().'&';
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
     * Sort-link targets/titles for the author and last-post columns —
     * toggling direction relative to the active sort.
     *
     * @return array{first: string, firstTitle: string, last: string, lastTitle: string}
     */
    public function sortToggles(): array
    {
        $firstDesc = $this->sort === 'firstpostdesc';
        $lastAsc = $this->sort === 'lastpostasc';

        return [
            'first' => $firstDesc ? 'firstpostasc' : 'firstpostdesc',
            'firstTitle' => $firstDesc ? __('legacy/forums.title_order_topic_asc') : __('legacy/forums.title_order_topic_desc'),
            'last' => $lastAsc ? 'lastpostdesc' : 'lastpostasc',
            'lastTitle' => $lastAsc ? __('legacy/forums.title_order_post_desc') : __('legacy/forums.title_order_post_asc'),
        ];
    }
}
