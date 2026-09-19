<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\ViewModels\Forum\ForumIndexViewModel;
use App\ViewModels\Forum\ForumSearchViewModel;
use App\ViewModels\Forum\TopicListViewModel;
use App\ViewModels\Forum\UnreadTopicsViewModel;

/**
 * ViewModel for the forum page.
 *
 * Returned by ForumPageService::build().
 */
final class ForumPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $compose
     * @param  array<string, mixed>|null  $viewtopic
     */
    public function __construct(
        public readonly array $curUser,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $sitename,
        public readonly int $postsperpage,
        public readonly int $topicsperpage,
        public readonly string $todayDate,
        public readonly ?array $compose = null,
        public readonly ?array $viewtopic = null,
        public readonly ?TopicListViewModel $viewforum = null,
        public readonly ?UnreadTopicsViewModel $viewunread = null,
        public readonly ?ForumSearchViewModel $search = null,
        public readonly ?ForumIndexViewModel $forums = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
