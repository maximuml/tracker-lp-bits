<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
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
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
    ) {}

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): ForumPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $lang = (array) ($this->globals->get('lang_forums') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        // Global variables previously set by the procedural partial.
        $mainConfig = SiteConfig::current()->main;
        $maxsubjectlength = $mainConfig->maxSubjectLength(100);
        $postsperpage = (int) ($curUser['postsperpage'] ?? 0);
        if (! $postsperpage) {
            $postsperpage = $mainConfig->forumPostsPerPage(10);
        }
        $topicsperpage = (int) ($curUser['topicsperpage'] ?? 0);
        if (! $topicsperpage) {
            $topicsperpage = $mainConfig->forumTopicsPerPage(20);
        }
        $todayDate = date('Y-m-d');
        $this->globals->set('maxsubjectlength', $maxsubjectlength);
        $this->globals->set('postsperpage', $postsperpage);
        $this->globals->set('topicsperpage', $topicsperpage);
        $this->globals->set('today_date', $todayDate);

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
                $compose = $this->composeService->buildNewTopic($lang, $request);
                $action = 'newtopic';
                break;
            case 'quotepost':
                $compose = $this->composeService->buildQuotePost($lang, $curUser, $request);
                $action = 'quotepost';
                break;
            case 'reply':
                $compose = $this->composeService->buildReply($lang, $request);
                $action = 'reply';
                break;
            case 'editpost':
                $compose = $this->composeService->buildEditPost($lang, $curUser, $request);
                $action = 'editpost';
                break;
            case 'viewtopic':
                $viewtopic = $this->topicViewService->buildViewTopic($lang, $curUser, $userId, $request, $postsperpage);
                $action = 'viewtopic';
                break;
            case 'viewforum':
                $viewforum = $this->listingService->buildViewForum($lang, $curUser, $request, $topicsperpage, $postsperpage);
                $action = 'viewforum';
                break;
            case 'viewunread':
                $viewunread = $this->listingService->buildViewUnread($lang, $curUser);
                $action = 'viewunread';
                break;
            case 'search':
                $search = $this->listingService->buildSearch($lang, $topicsperpage);
                $action = 'search';
                break;
            default:
                if ($action !== '') {
                    LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_unknown_action'] ?? '');
                }
                $forums = $this->indexService->buildForumsIndex($lang, $curUser, $userId);
                $action = 'forums';
                break;
        }

        return new ForumPageViewModel(
            lang: $lang,
            curUser: $curUser,
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
