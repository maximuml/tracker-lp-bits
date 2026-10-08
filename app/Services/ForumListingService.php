<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Enums\UserTimeType;
use App\Repositories\TopicRepository;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\Log;
use App\Support\PageResponses;
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
        private readonly ?NexusCache $cache,
        private readonly TopicRepository $topicRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Build the view-forum section.
     */
    public function buildViewForum(CurrentUser $curUser, Request $request, int $topicsperpage, int $postsperpage): TopicListViewModel
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        PageResponses::assertId($forumid, true);

        $row = $this->index->getForumRow($forumid);
        if (! $row) {
            Log::writeWithContext('User '.$curUser->username().','.((string) $curUser->value('ip', ''))." is trying to visit forum that doesn't exist", 'mod');
            PageResponses::abort(__('forums.std_forum_error'), __('forums.std_forum_not_found'));
        }
        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            PageResponses::permissionDenied();
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

        $enabletooltipTweak = SiteConfig::current()->tweak->enableTooltip() ? 'yes' : 'no';
        $tooltipsEnabled = $enabletooltipTweak === 'yes' && ! $curUser->no('showlastpost');

        $topics = [];
        $tooltips = [];
        $counter = 0;

        $postCounts = [];
        $uncachedTopicIds = [];
        $topicIds = $topicRows->map(fn ($topic) => (int) $topic->id)->all();
        $cachedCounts = $topicIds === []
            ? []
            : ($this->cache?->getMany(array_map(fn ($id) => 'topic_'.$id.'_post_count', $topicIds)) ?? []);
        foreach ($topicIds as $topicid) {
            $cached = $cachedCounts['topic_'.$topicid.'_post_count'] ?? false;
            if ($cached !== false) {
                $postCounts[$topicid] = (int) $cached;
            } else {
                $uncachedTopicIds[] = $topicid;
            }
        }
        if ($uncachedTopicIds !== []) {
            $postCounts += $this->postRepository->countTopicPostsBatch($uncachedTopicIds);
            foreach ($uncachedTopicIds as $topicid) {
                $this->cache?->put('topic_'.$topicid.'_post_count', $postCounts[$topicid] ?? 0, 3600);
            }
        }

        $postIds = [];
        foreach ($topicRows as $topic) {
            $topicarr = $topic->toArray();
            foreach (['lastpost', 'firstpost'] as $col) {
                if (! empty($topicarr[$col])) {
                    $postIds[] = (int) $topicarr[$col];
                }
            }
        }
        $postRows = $postIds === []
            ? []
            : ($this->cache?->getMany(array_map(fn ($id) => 'post_'.$id.'_content', array_values(array_unique($postIds)))) ?? []);
        $resolvePost = static function (int $postId) use ($postRows): array {
            $row = $postRows['post_'.$postId.'_content'] ?? false;
            if (! is_array($row)) {
                $row = Forum::postRowWithContext($postId) ?? [];
            }

            return $row;
        };

        $ttKeys = [];
        foreach ($postRows as $postRow) {
            if (is_array($postRow)) {
                $ttKeys[] = 'fmt_tt_'.md5(self::tooltipText((string) ($postRow['body'] ?? '')));
            }
        }
        $renderedTt = $ttKeys === []
            ? []
            : ($this->cache?->getMany(array_values(array_unique($ttKeys))) ?? []);
        $renderTt = function (string $body) use (&$renderedTt): string {
            $truncated = self::tooltipText($body);
            $key = 'fmt_tt_'.md5($truncated);
            $hit = $renderedTt[$key] ?? false;
            if (is_string($hit)) {
                return $hit;
            }
            $html = (string) Format::formatComment($truncated, true, false, false, true, 600, false, false);
            $this->cache?->put($key, $html, 86400);
            $renderedTt[$key] = $html;

            return $html;
        };

        foreach ($topicRows as $topic) {
            $topicarr = $topic->toArray();
            $topicid = (int) ($topicarr['id'] ?? 0);
            $locked = (bool) ($topicarr['locked'] ?? false);
            $hlcolor = (int) ($topicarr['hlcolor'] ?? 0);

            $posts = $postCounts[$topicid] ?? 0;

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

            $arr = $resolvePost((int) ($topicarr['lastpost'] ?? 0));
            $lppostid = (int) ($arr['id'] ?? 0);
            $lpuserid = (int) ($arr['userid'] ?? 0);
            $lpadded = (string) ($arr['added'] ?? '');
            $tooltipId = null;
            if ($tooltipsEnabled) {
                if ($curUser->value('timetype', 1) != UserTimeType::TIMEALIVE->value) {
                    $lastposttime = __('forums.text_at_time').$lpadded;
                } else {
                    $lastposttime = __('forums.text_blank').Time::format($lpadded, true, false, true);
                }
                $lptext = $renderTt((string) ($arr['body'] ?? ''));
                $tooltipId = 'lastpost_'.$counter;
                $tooltips[] = [
                    'id' => $tooltipId,
                    'content' => SafeHtml::fromTrustedHtml(__('forums.text_last_posted_by').UserDisplay::username($lpuserid).$lastposttime),
                    'contentTail' => SafeHtml::fromTrustedHtml($lptext),
                ];
            }

            $arr = $resolvePost((int) ($topicarr['firstpost'] ?? 0));
            $firstAdded = (string) ($arr['added'] ?? '');
            $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

            $jumpToPostId = null;
            if ($lastpostread >= $lppostid) {
                $state = $locked ? 'locked' : 'read';
            } else {
                $state = $locked ? 'lockednew' : 'unread';
                if ($lastpostread != (int) $curUser->value('last_catchup', 0)) {
                    $jumpToPostId = $lastpostread;
                }
            }

            $topics[] = new TopicRow(
                id: $topicid,
                forumId: $forumid,
                subject: SafeHtml::fromTrustedHtml(Format::highlight($search, htmlspecialchars((string) ($topicarr['subject'] ?? '')))),
                hlcolor: $hlcolor,
                sticky: ($topicarr['sticky'] ?? 0) == 1,
                state: $state,
                visiblePages: $visiblePages,
                jumpToPostId: $jumpToPostId,
                tooltipId: $tooltipId,
                author: UserDisplay::username((int) ($arr['userid'] ?? 0)),
                firstAdded: substr($firstAdded, 0, 10),
                firstAddedRecent: strtotime($firstAdded) + 86400 > (int) (defined('TIMENOW') ? constant('TIMENOW') : time()),
                replies: max(0, $posts - 1),
                views: (int) ($topicarr['views'] ?? 0),
                lastPostAt: $lpadded,
                lastPoster: UserDisplay::username($lpuserid),
            );
            $counter++;
        }

        $maypost = UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && UserDisplay::currentClass() >= (int) ($row['minclasscreate'] ?? 0) && $curUser->yes('forumpost');

        return new TopicListViewModel(
            siteName: SiteConfig::current()->basic->siteName(),
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
     */
    public function buildViewUnread(CurrentUser $curUser): UnreadTopicsViewModel
    {
        $beforepostid = (int) (request()->query('beforepostid') ?? 0);
        $maxresults = 25;
        $lastCatchup = (int) $curUser->value('last_catchup', 0);
        $unreadTopics = $this->topicRepository->getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

        $topics = [];
        $n = 0;
        $uc = UserDisplay::currentClass();
        $topiclastpost = 0;

        foreach ($unreadTopics as $topic) {
            $arr = $topic->toArray();
            $topiclastpost = (int) ($arr['lastpost'] ?? 0);
            $topicid = (int) ($arr['id'] ?? 0);

            $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

            if ($lastpostread >= $topiclastpost) {
                continue;
            }

            $forumid = (int) ($arr['forumid'] ?? 0);
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
                subject: SafeHtml::fromTrustedHtml(htmlspecialchars((string) ($arr['subject'] ?? ''))),
                hlcolor: (int) ($arr['hlcolor'] ?? 0),
                jumpToPostId: ($lastpostread > 0 && $lastpostread != $lastCatchup) ? $lastpostread : null,
                forumId: $forumid,
                forumName: (string) ($a['name'] ?? ''),
            );
        }

        return new UnreadTopicsViewModel(
            siteName: SiteConfig::current()->basic->siteName(),
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
            $hits = $this->postRepository->countForumSearchPosts($keywords, (int) UserDisplay::currentClass());
        }

        if ($hits > 0) {
            [, , , $offset, $perpage, $page] = Pagination::pager($topicsperpage, $hits, '/forums?action=search&keywords='.rawurlencode($keywords).'&');
            $rows = $this->postRepository->searchForumPosts($keywords, (int) UserDisplay::currentClass(), (int) $offset, (int) $perpage);
            $pages = (int) max(1, (int) ceil($hits / max(1, (int) $perpage)));

            foreach ($rows as $post) {
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

    private static function tooltipText(string $body): string
    {
        return mb_substr($body, 0, 100, 'UTF-8').(mb_strlen($body, 'UTF-8') > 100 ? ' ......' : '');
    }
}
