<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
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
    ) {}

    /**
     * Build the data for the requested action.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (app(CurrentUser::class)->get() ?? []);
        $lang = (array) (app(Globals::class)->get('lang_forums') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        // Global variables previously set by the procedural partial.
        $maxsubjectlength = 100;
        $postsperpage = (int) ($curUser['postsperpage'] ?? 0);
        if (! $postsperpage) {
            $forumpostsperpage = app(Globals::class)->get('forumpostsperpage');
            if (is_numeric($forumpostsperpage)) {
                $postsperpage = (int) $forumpostsperpage;
            } else {
                $postsperpage = 10;
            }
        }
        $topicsperpage = (int) ($curUser['topicsperpage'] ?? 0);
        if (! $topicsperpage) {
            $forumtopicsperpageMain = app(Globals::class)->get('forumtopicsperpage_main');
            if (is_numeric($forumtopicsperpageMain)) {
                $topicsperpage = (int) $forumtopicsperpageMain;
            } else {
                $topicsperpage = 20;
            }
        }
        $todayDate = date('Y-m-d');
        app(Globals::class)->set('maxsubjectlength', $maxsubjectlength);
        app(Globals::class)->set('postsperpage', $postsperpage);
        app(Globals::class)->set('topicsperpage', $topicsperpage);
        app(Globals::class)->set('today_date', $todayDate);

        $action = htmlspecialchars(trim((string) request()->query('action')));

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'sitename' => (string) app(Globals::class)->get('SITENAME', ''),
            'postsperpage' => $postsperpage,
            'topicsperpage' => $topicsperpage,
            'todayDate' => $todayDate,
        ];

        // catchup is a query-flag action, not a dispatched section.
        if (((request()->query('catchup') !== null)) && request()->query('catchup') == 1) {
            $this->indexService->catchUp();
        }

        switch ($action) {
            case 'newtopic':
                $data['compose'] = $this->composeService->buildNewTopic($lang, $request);
                $data['action'] = 'newtopic';
                break;
            case 'quotepost':
                $data['compose'] = $this->composeService->buildQuotePost($lang, $curUser, $request);
                $data['action'] = 'quotepost';
                break;
            case 'reply':
                $data['compose'] = $this->composeService->buildReply($lang, $request);
                $data['action'] = 'reply';
                break;
            case 'editpost':
                $data['compose'] = $this->composeService->buildEditPost($lang, $curUser, $request);
                $data['action'] = 'editpost';
                break;
            case 'viewtopic':
                $data['viewtopic'] = $this->topicViewService->buildViewTopic($lang, $curUser, $userId, $request, $postsperpage);
                $data['action'] = 'viewtopic';
                break;
            case 'viewforum':
                $data['viewforum'] = $this->listingService->buildViewForum($lang, $curUser, $request, $topicsperpage, $postsperpage);
                $data['action'] = 'viewforum';
                break;
            case 'viewunread':
                $data['viewunread'] = $this->listingService->buildViewUnread($lang, $curUser);
                $data['action'] = 'viewunread';
                break;
            case 'search':
                $data['search'] = $this->listingService->buildSearch($lang, $topicsperpage);
                $data['action'] = 'search';
                break;
            default:
                if ($action !== '') {
                    LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_unknown_action'] ?? '');
                }
                $data['forums'] = $this->indexService->buildForumsIndex($lang, $curUser, $userId);
                $data['action'] = 'forums';
                break;
        }

        return $data;
    }
}
