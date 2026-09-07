<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the forum page.
 *
 * Returned by ForumPageService::build().
 */
final class ForumPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $compose
     * @param  array<string, mixed>|null  $viewtopic
     * @param  array<string, mixed>|null  $viewforum
     * @param  array<string, mixed>|null  $viewunread
     * @param  array<string, mixed>|null  $search
     * @param  array<string, mixed>|null  $forums
     */
    public function __construct(
        public readonly array $lang,
        public readonly array $curUser,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $sitename,
        public readonly int $postsperpage,
        public readonly int $topicsperpage,
        public readonly string $todayDate,
        public readonly ?array $compose = null,
        public readonly ?array $viewtopic = null,
        public readonly ?array $viewforum = null,
        public readonly ?array $viewunread = null,
        public readonly ?array $search = null,
        public readonly ?array $forums = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
            'curUser' => $this->curUser,
            'userId' => $this->userId,
            'action' => $this->action,
            'sitename' => $this->sitename,
            'postsperpage' => $this->postsperpage,
            'topicsperpage' => $this->topicsperpage,
            'todayDate' => $this->todayDate,
            'compose' => $this->compose,
            'viewtopic' => $this->viewtopic,
            'viewforum' => $this->viewforum,
            'viewunread' => $this->viewunread,
            'search' => $this->search,
            'forums' => $this->forums,
        ];
    }
}
