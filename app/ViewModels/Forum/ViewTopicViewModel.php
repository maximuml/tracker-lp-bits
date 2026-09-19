<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;
use App\View\Components\Pagination;

/**
 * The `viewtopic` section: breadcrumb, paginated posts, moderator
 * toolbox and quick reply.
 *
 * `page` is the 0-based legacy `page=` query value — `x-forum.pager`
 * maps it to the 1-based display window.
 */
final readonly class ViewTopicViewModel
{
    /**
     * @param  list<PostViewModel>  $posts
     * @param  list<array{id: int, name: string}>  $moveForums  target forums for the mod move-select
     */
    public function __construct(
        public int $topicid,
        public int $forumid,
        public string $forumname,
        public string $sitename,
        public SafeHtml $subject,
        public bool $locked,
        public bool $sticky,
        public int $views,
        public bool $mayPost,
        public bool $isMod,
        public int $authorid,
        public string $requestUri,
        public int $page,
        public int $pages,
        public array $posts,
        public array $moveForums,
        public SafeHtml $highlightColorOptions,
        public ?SafeHtml $quickReply,
        public ?SafeHtml $deniedNotice,
        public SafeHtml $keyScript,
        public SafeHtml $frameOpen,
        public SafeHtml $frameClose,
    ) {}

    public function pagerHref(): string
    {
        $href = '?action=viewtopic&topicid='.$this->topicid;

        return $this->authorid !== 0 ? $href.'&authorid='.$this->authorid.'&' : $href.'&';
    }

    /**
     * 1-based display window for `x-forum.pager` (maps back to the
     * 0-based `page=` param).
     *
     * @return list<int|string>
     */
    public function pagerItems(): array
    {
        return Pagination::window($this->page + 1, $this->pages);
    }
}
