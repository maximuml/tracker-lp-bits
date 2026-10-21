<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Support\Config\SiteConfig;
use App\Support\PageResponses;
use App\ViewModels\ForumPageViewModel;
use Illuminate\Http\Request;

/**
 * Prepares section data for the forums page, replacing the legacy
 * forum_forums_content.php partial with typed Blade-rendered sections.
 *
 * Sections (action-dispatched):
 *  - newtopic / reply / quotepost / editpost: compose frame form
 *  - viewtopic:  single topic with paginated posts + mod toolbox
 *  - viewforum:  forum view with topic list, sort + search
 *  - viewunread: list of topics with unread posts
 *  - search:     forum keyword search form + results
 *  - forums:     default forum index (overforums + forums list + stats)
 */
final class ForumPageService
{
    public function __construct(
        private readonly ForumIndexService $indexService,
        private readonly ForumComposeService $composeService,
        private readonly ForumTopicViewService $topicViewService,
        private readonly ForumListingService $listingService,
        private readonly ActorContext $actor,
    ) {}

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): ForumPageViewModel
    {
        $actor = $this->actor;
        $userId = $actor->id;

        $mainConfig = SiteConfig::current()->main;
        $postsperpage = (int) ($this->actor->value('postsperpage', 0));
        if (! $postsperpage) {
            $postsperpage = $mainConfig->forumPostsPerPage(10);
        }
        $topicsperpage = (int) ($this->actor->value('topicsperpage', 0));
        if (! $topicsperpage) {
            $topicsperpage = $mainConfig->forumTopicsPerPage(20);
        }
        $todayDate = date('Y-m-d');

        $action = htmlspecialchars(trim((string) request()->query('action')));

        $sitename = SiteConfig::current()->basic->siteName();

        // catchup is a query-flag action, not a dispatched section.
        if (((request()->query('catchup') !== null)) && request()->query('catchup') == 1) {
            $this->indexService->catchUp();
        }

        $compose = null;
        $viewtopic = null;
        $viewforum = null;
        $viewunread = null;
        $search = null;
        $forums = null;

        switch ($action) {
            case 'newtopic':
                $compose = $this->composeService->buildNewTopic($request);
                $action = 'newtopic';
                break;
            case 'quotepost':
                $compose = $this->composeService->buildQuotePost($actor, $request);
                $action = 'quotepost';
                break;
            case 'reply':
                $compose = $this->composeService->buildReply($request);
                $action = 'reply';
                break;
            case 'editpost':
                $compose = $this->composeService->buildEditPost($actor, $request);
                $action = 'editpost';
                break;
            case 'viewtopic':
                $viewtopic = $this->topicViewService->buildViewTopic($actor, $userId, $request, $postsperpage);
                $action = 'viewtopic';
                break;
            case 'viewforum':
                $viewforum = $this->listingService->buildViewForum($actor, $request, $topicsperpage, $postsperpage);
                $action = 'viewforum';
                break;
            case 'viewunread':
                $viewunread = $this->listingService->buildViewUnread($actor);
                $action = 'viewunread';
                break;
            case 'search':
                $search = $this->listingService->buildSearch($topicsperpage);
                $action = 'search';
                break;
            default:
                if ($action !== '') {
                    PageResponses::abort(__('forums.std_forum_error'), __('forums.std_unknown_action'));
                }
                $forums = $this->indexService->buildForumsIndex($actor, $userId);
                $action = 'forums';
                break;
        }

        return new ForumPageViewModel(
            curUser: $actor->toLegacyArray(),
            userId: $userId,
            action: $action,
            sitename: $sitename,
            postsperpage: $postsperpage,
            topicsperpage: $topicsperpage,
            todayDate: $todayDate,
            compose: $compose,
            viewtopic: $viewtopic,
            viewforum: $viewforum,
            viewunread: $viewunread,
            search: $search,
            forums: $forums,
        );
    }
}
