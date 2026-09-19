<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Enums\UserTimeType;
use App\Repositories\TopicRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\LegacyYesNo;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use App\ViewModels\Forum\ForumSearchViewModel;
use App\ViewModels\Forum\SearchResultRow;
use App\ViewModels\Forum\TopicListViewModel;
use App\ViewModels\Forum\TopicRow;
use App\ViewModels\Forum\UnreadTopicRow;
use App\ViewModels\Forum\UnreadTopicsViewModel;
use Illuminate\Http\Request;

/**
 * Builds the forum listing sections (view-forum, view-unread, search)
 * for the forums page.
 *
 * ADR 0021: the service returns typed view models — no markup is built
 * here. Trusted HTML is limited to `UserDisplay::username()` rich names
 * and `Format::highlight()`/`formatComment()` output, which are
 * documented SafeHtml boundaries on the row DTOs.
 */
final class ForumListingService
{
    public function __construct(
        private readonly ForumIndexService $index,
        private readonly Globals $globals,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly TopicRepository $topicRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Build the view-forum section.
     *
     * @param  array<string, mixed>  $curUser
     */
    public function buildViewForum(array $curUser, Request $request, int $topicsperpage, int $postsperpage): TopicListViewModel
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        LegacyResponse::assertId($forumid, true);

        $row = $this->index->getForumRow($forumid);
        if (! $row) {
            Log::writeWithContext('User '.($curUser['username'] ?? '').','.($curUser['ip'] ?? '')." is trying to visit forum that doesn't exist", 'mod');
            LegacyResponse::abort(__('legacy/forums.std_forum_error'), __('legacy/forums.std_forum_not_found'));
        }
        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            LegacyResponse::permissionDenied();
        }

        $forumname = (string) ($row['name'] ?? '');
        $forummoderators = Forum::moderatorsWithContext($forumid, false);
        $searchRaw = request()->query('search') ?? '';
        $search = trim(is_scalar($searchRaw) ? (string) $searchRaw : '');

        $sort = (string) (request()->query('sort') ?? 'lastpostdesc');
        [$sortColumn, $sortDirection] = match ($sort) {
            'firstpostasc' => ['firstpost', 'asc'],
            'firstpostdesc' => ['firstpost', 'desc'],
            'lastpostasc' => ['lastpost', 'asc'],
            default => ['lastpost', 'desc'],
        };

        $topicResult = $this->topicRepository->getTopicsByForum($forumid, $search, $sortColumn, $sortDirection, 0, 0);
        $num = (int) $topicResult['count'];

        [, , , $offset, $perpage, $page] = Pagination::pager($topicsperpage, $num, '?action=viewforum&forumid='.$forumid.($search === '' ? '' : '&search='.rawurlencode($search)).'&');
        $topicResult = $this->topicRepository->getTopicsByForum($forumid, $search, $sortColumn, $sortDirection, (int) $offset, (int) $perpage);
        $topicRows = $topicResult['rows'];

        $enabletooltipTweak = (string) $this->globals->get('enabletooltip_tweak', '');
        $tooltipsEnabled = $enabletooltipTweak === 'yes' && ! LegacyYesNo::isNo($curUser['showlastpost'] ?? null);

        $topics = [];
        $tooltips = [];
        $counter = 0;

        foreach ($topicRows as $topic) {
            $topicarr = $topic->toArray();
            $topicid = (int) $topicarr['id'];
            $locked = (bool) $topicarr['locked'];
            $hlcolor = (int) $topicarr['hlcolor'];

            $posts = $this->legacyRedisCache?->get_value('topic_'.$topicid.'_post_count');
            if (! $posts) {
                $posts = $this->postRepository->countTopicPosts((int) $topicid);
                $this->legacyRedisCache?->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
            }
            $posts = (int) $posts;

            $tpages = (int) floor($posts / max(1, $postsperpage));
            if ($tpages * $postsperpage != $posts) {
                $tpages++;
            }

            $visiblePages = [];
            if ($tpages > 1) {
                $dotted = 0;
                $dotspace = 4;
                $dotend = $tpages - $dotspace;
                for ($i = 1; $i <= $tpages; $i++) {
                    if ($i > $dotspace && $i <= $dotend) {
                        if (! $dotted) {
                            $visiblePages[] = '…';
                        }
                        $dotted = 1;

                        continue;
                    }
                    $visiblePages[] = $i;
                }
            }

            $arr = Forum::postRowWithContext((int) $topicarr['lastpost']);
            $lppostid = (int) ($arr['id'] ?? 0);
            $lpuserid = (int) ($arr['userid'] ?? 0);
            $lpadded = (string) ($arr['added'] ?? '');
            $tooltipId = null;
            if ($tooltipsEnabled) {
                if (($curUser['timetype'] ?? 1) != UserTimeType::TIMEALIVE->value) {
                    $lastposttime = __('legacy/forums.text_at_time').$lpadded;
                } else {
                    $lastposttime = __('legacy/forums.text_blank').Time::format($lpadded, true, false, true);
                }
                $lptext = Format::formatComment(mb_substr((string) ($arr['body'] ?? ''), 0, 100, 'UTF-8').(mb_strlen((string) ($arr['body'] ?? ''), 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                $tooltipId = 'lastpost_'.$counter;
                $tooltips[] = [
                    'id' => $tooltipId,
                    'content' => SafeHtml::fromTrustedHtml(__('legacy/forums.text_last_posted_by').UserDisplay::username($lpuserid).$lastposttime.'<br />'.$lptext),
                ];
            }

            $arr = Forum::postRowWithContext((int) $topicarr['firstpost']);
            $firstAdded = (string) ($arr['added'] ?? '');
            $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

            $jumpToPostId = null;
            if ($lastpostread >= $lppostid) {
                $state = $locked ? 'locked' : 'read';
            } else {
                $state = $locked ? 'lockednew' : 'unread';
                if ($lastpostread != (int) ($curUser['last_catchup'] ?? 0)) {
                    $jumpToPostId = $lastpostread;
                }
            }

            $topics[] = new TopicRow(
                id: $topicid,
                forumId: $forumid,
                subject: SafeHtml::fromTrustedHtml(Format::highlight($search, htmlspecialchars((string) $topicarr['subject']))),
                hlcolor: $hlcolor,
                sticky: $topicarr['sticky'] == 1,
                state: $state,
                visiblePages: $visiblePages,
                jumpToPostId: $jumpToPostId,
                tooltipId: $tooltipId,
                author: UserDisplay::username((int) ($arr['userid'] ?? 0)),
                firstAdded: substr($firstAdded, 0, 10),
                firstAddedRecent: strtotime($firstAdded) + 86400 > (int) (defined('TIMENOW') ? constant('TIMENOW') : time()),
                replies: max(0, $posts - 1),
                views: (int) $topicarr['views'],
                lastPostAt: $lpadded,
                lastPoster: UserDisplay::username($lpuserid),
            );
            $counter++;
        }

        $maypost = UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && UserDisplay::currentClass() >= (int) ($row['minclasscreate'] ?? 0) && LegacyYesNo::isYes($curUser['forumpost'] ?? null);

        return new TopicListViewModel(
            siteName: (string) $this->globals->get('SITENAME', ''),
            forumId: $forumid,
            forumName: $forumname,
            mayPost: $maypost,
            moderators: $forummoderators !== '' ? SafeHtml::fromTrustedHtml($forummoderators) : null,
            search: $search,
            sort: $sort,
            topics: $topics,
            page: (int) $page,
            pages: (int) max(1, (int) ceil($num / max(1, $perpage))),
            tooltips: $tooltips,
        );
    }

    /**
     * Build the view-unread-posts section.
     *
     * @param  array<string, mixed>  $curUser
     */
    public function buildViewUnread(array $curUser): UnreadTopicsViewModel
    {
        $beforepostid = (int) (request()->query('beforepostid') ?? 0);
        $maxresults = 25;
        $lastCatchup = (int) ($curUser['last_catchup'] ?? 0);
        $unreadTopics = $this->topicRepository->getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

        $topics = [];
        $n = 0;
        $uc = UserDisplay::currentClass();
        $topiclastpost = 0;

        foreach ($unreadTopics as $topic) {
            $arr = $topic->toArray();
            $topiclastpost = (int) $arr['lastpost'];
            $topicid = (int) $arr['id'];

            $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

            if ($lastpostread >= $topiclastpost) {
                continue;
            }

            $forumid = (int) $arr['forumid'];
            $a = $this->index->getForumRow($forumid);
            if ($uc < (int) ($a['minclassread'] ?? 0)) {
                continue;
            }
            $n++;
            if ($n > $maxresults) {
                break;
            }

            $topics[] = new UnreadTopicRow(
                topicId: $topicid,
                subject: SafeHtml::fromTrustedHtml(htmlspecialchars((string) $arr['subject'])),
                hlcolor: (int) $arr['hlcolor'],
                jumpToPostId: ($lastpostread > 0 && $lastpostread != $lastCatchup) ? $lastpostread : null,
                forumId: $forumid,
                forumName: (string) ($a['name'] ?? ''),
            );
        }

        return new UnreadTopicsViewModel(
            siteName: (string) $this->globals->get('SITENAME', ''),
            topics: $topics,
            moreBeforePostId: $n > $maxresults ? $topiclastpost : null,
        );
    }

    /**
     * Build the forum search section.
     */
    public function buildSearch(int $topicsperpage): ForumSearchViewModel
    {
        $keywords = trim((string) (request()->query('keywords') ?? ''));
        $keywordsEsc = htmlspecialchars($keywords);
        $searched = $keywords !== '';
        $hits = 0;
        $results = [];
        $page = 0;
        $pages = 0;

        if ($searched) {
            $searchResult = $this->postRepository->searchForumPosts($keywordsEsc, (int) UserDisplay::currentClass(), 0, 0);
            $hits = (int) $searchResult['hits'];
        }

        if ($hits > 0) {
            [, , , $offset, $perpage, $page] = Pagination::pager($topicsperpage, $hits, 'forums.php?action=search&keywords='.rawurlencode($keywords).'&');
            $searchResult = $this->postRepository->searchForumPosts($keywordsEsc, (int) UserDisplay::currentClass(), (int) $offset, (int) $perpage);
            $pages = (int) max(1, (int) ceil($hits / max(1, (int) $perpage)));

            foreach ($searchResult['rows'] as $post) {
                $post = (array) $post;
                $results[] = new SearchResultRow(
                    postId: (int) $post['id'],
                    topicId: (int) $post['topicid'],
                    subject: SafeHtml::fromTrustedHtml(Format::highlight($keywordsEsc, htmlspecialchars((string) $post['subject']))),
                    hlcolor: (int) $post['hlcolor'],
                    forumId: (int) $post['forumid'],
                    forumName: (string) $post['forumname'],
                    added: (string) $post['added'],
                    poster: UserDisplay::username((int) $post['userid']),
                );
            }
        }

        return new ForumSearchViewModel(
            keywords: $keywords,
            searched: $searched,
            hits: $hits,
            results: $results,
            page: (int) $page,
            pages: $pages,
            imageUrl: Forum::picFolderWithContext().'/search_button.gif',
        );
    }
}
