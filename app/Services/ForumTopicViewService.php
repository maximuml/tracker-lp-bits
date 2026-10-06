<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClickTopic;
use App\Models\User;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Ratio;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use App\Support\YesNo;
use App\ViewModels\Forum\PostViewModel;
use App\ViewModels\Forum\ViewTopicViewModel;
use Illuminate\Http\Request;

/**
 * Builds the view-topic section (single topic with paginated posts
 * and mod toolbox) for the forums page.
 */
final class ForumTopicViewService
{
    public function __construct(
        private readonly ForumIndexService $index,
        private readonly ForumRepositoryInterface $forumRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly TopicRepository $topicRepository,
        private readonly TopicReadStateRepository $readStateRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Build the view-topic section.
     *
     * @param  array<string, mixed>  $curUser
     */
    public function buildViewTopic(array $curUser, int $userId, Request $request, int $postsperpage): ViewTopicViewModel
    {
        $highlight = trim((string) ($request->query('highlight') ?? ''));
        $topicid = (int) ($request->query('topicid') ?? 0);
        LegacyResponse::assertId($topicid, true);
        $page = is_string($val = $request->query('page')) ? $val : 0;
        $authorid = (int) ($request->query('authorid') ?? 0);

        $topic = $this->topicRepository->getTopic($topicid);
        if (! $topic) {
            LegacyResponse::abort(__('legacy/forums.std_forum_error'), __('legacy/forums.std_topic_not_found'));
        }
        $arr = $topic->toArray();

        $forumid = (int) ($arr['forumid'] ?? 0);
        $locked = (bool) ($arr['locked'] ?? false);
        $orgsubject = (string) ($arr['subject'] ?? '');
        $subject = SafeHtml::fromTrustedHtml(
            $highlight !== ''
                ? Format::highlight(htmlspecialchars($highlight), $orgsubject)
                : htmlspecialchars($orgsubject),
        );
        $sticky = ($arr['sticky'] ?? 0) == 1;
        $views = (int) ($arr['views'] ?? 0);

        $row = $this->index->getForumRow($forumid);
        $forumname = (string) ($row['name'] ?? '');
        $isForummod = Forum::isModerator($forumid, 'forum');
        $isMod = Permission::can(PermissionEnum::POST_MANAGE) || $isForummod;

        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_unpermitted_viewing_topic'));
        }
        $maypost = ((UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && ! $locked) || $isMod) && YesNo::isYes($curUser['forumpost'] ?? null);

        $this->topicRepository->incrementTopicViews($topicid);

        $postcount = $this->postRepository->countTopicPosts($topicid, $authorid ?: null);
        if (! $authorid) {
            $this->legacyRedisCache?->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);
        }

        $perpage = $postsperpage;
        $pages = (int) ceil($postcount / max(1, $perpage));

        if ((isset($page[0])) && $page[0] == 'p') {
            $findpost = substr($page, 1);
            $postIds = $this->postRepository->getTopicPostIds($topicid, $authorid ?: null);
            $i = array_search($findpost, $postIds);
            if ($i === false) {
                $i = 0;
            }
            $page = (int) floor((int) $i / $perpage);
        }
        if ($page === 'last') {
            $page = $pages - 1;
        } elseif ($page < 0) {
            $page = 0;
        } elseif ($page > $pages - 1) {
            $page = $pages - 1;
        } elseif (($curUser['clicktopic'] ?? 1) == UserClickTopic::FIRSTPAGE->value) {
            $page = 0;
        } else {
            $page = $pages - 1;
        }
        $page = (int) $page;

        $offset = $page * $perpage;

        $postRows = $this->postRepository->getTopicPosts($topicid, $authorid ?: null, $offset, $perpage);
        $pc = $postRows->count();
        $allPosts = [];
        $uidArr = [];
        foreach ($postRows as $postObj) {
            $postArr = $postObj->toArray();
            $allPosts[] = $postArr;
            $uidArr[$postArr['userid'] ?? 0] = 1;
        }
        $uidArr = array_keys($uidArr);

        $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
        $userInfoArr = $this->forumRepository->getUsersByIds($uidArr, $neededColumns);
        UserDisplay::preload(array_merge($uidArr, array_filter(array_map(fn ($p) => (int) ($p['editedby'] ?? 0), $allPosts))));

        $postCounts = [];
        $uncachedPosterIds = [];
        $cachedCounts = $uidArr === []
            ? []
            : ($this->legacyRedisCache?->get_values(array_map(fn ($id) => 'user_'.$id.'_post_count', $uidArr)) ?? []);
        foreach ($uidArr as $posterId) {
            $cached = $cachedCounts['user_'.$posterId.'_post_count'] ?? false;
            if ($cached !== false) {
                $postCounts[(int) $posterId] = (int) $cached;
            } else {
                $uncachedPosterIds[] = (int) $posterId;
            }
        }
        if ($uncachedPosterIds !== []) {
            $postCounts += $this->postRepository->countUserPostsBatch($uncachedPosterIds);
            foreach ($uncachedPosterIds as $posterId) {
                $this->legacyRedisCache?->cache_value('user_'.$posterId.'_post_count', $postCounts[$posterId] ?? 0, 3600);
            }
        }
        $lpr = $this->index->getLastReadPostId($topicid, $curUser);

        $renderedFmt = [];
        if ($this->legacyRedisCache !== null && $allPosts !== []) {
            $fmtKeys = array_map(static fn ($p) => 'fmt_post_'.md5((string) ($p['body'] ?? '')), $allPosts);
            if (YesNo::isYes($curUser['signatures'] ?? null)) {
                foreach ($allPosts as $p) {
                    $sig = (string) (optional($userInfoArr->get((int) ($p['userid'] ?? 0)))->signature ?? '');
                    if ($sig !== '') {
                        $fmtKeys[] = 'fmt_sig_'.md5($sig);
                    }
                }
            }
            $renderedFmt = $this->legacyRedisCache->get_values(array_values(array_unique($fmtKeys)));
        }
        $renderFmt = function (string $key, callable $render) use (&$renderedFmt): SafeHtml {
            $hit = $renderedFmt[$key] ?? false;
            if (is_string($hit)) {
                return SafeHtml::fromTrustedHtml($hit);
            }
            $html = $render();
            $this->legacyRedisCache?->cache_value($key, (string) $html, 86400);
            $renderedFmt[$key] = (string) $html;

            return $html;
        };

        $posts = [];
        $pn = 0;
        foreach ($allPosts as $arr) {
            $pn++;
            $postid = (int) ($arr['id'] ?? 0);
            $posterid = (int) ($arr['userid'] ?? 0);

            $userInfo = $userInfoArr->get($posterid) ?: User::defaultUser();
            $arr2 = $userInfo->toArray();

            $forumposts = $postCounts[$posterid] ?? 0;

            $signature = YesNo::isYes($curUser['signatures'] ?? null) ? (string) ($arr2['signature'] ?? '') : '';
            $avatar = YesNo::isYes($curUser['avatars'] ?? null) ? (string) ($arr2['avatar'] ?? '') : '';
            if ($avatar === '') {
                $avatar = 'pic/default_avatar.png';
            }

            $isLast = $pn === $pc;
            if ($isLast && $postid > $lpr) {
                $this->readStateRepository->markPostRead($userId, $topicid, $postid, (int) ($curUser['last_catchup'] ?? 0));
                $this->legacyRedisCache?->delete_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list');
            }

            $canViewProtected = $pn + $offset <= 1 || Forum::canViewPost($userId, $arr);
            $bodyContent = $canViewProtected
                ? $renderFmt('fmt_post_'.md5((string) ($arr['body'] ?? '')), static fn () => Format::formatComment((string) ($arr['body'] ?? '')))
                : Format::formatComment((string) (__('legacy/forums.text_post_protected')));
            if ($highlight !== '') {
                $bodyContent = SafeHtml::fromTrustedHtml(Format::highlight(htmlspecialchars($highlight), (string) $bodyContent));
            }

            $editedBy = null;
            $editedAtRaw = null;
            if (Validators::isId($arr['editedby'] ?? null)) {
                $editedBy = SafeHtml::fromTrustedHtml(UserDisplay::username((int) ($arr['editedby'] ?? 0)));
                $editedAtRaw = $arr['editdate'] ?? null;
            }

            $dt = date('Y-m-d H:i:s', (int) (defined('TIMENOW') ? constant('TIMENOW') : time()) - 900);
            $className = strip_tags((string) UserClass::name((int) ($arr2['class'] ?? 0), false, false, true));

            $posts[] = new PostViewModel(
                id: $postid,
                number: $pn + $offset,
                isLast: $isLast,
                anchorUrl: '/forums?action=viewtopic&topicid='.$topicid.'&page=p'.$postid.'#pid'.$postid,
                addedRaw: (string) ($arr['added'] ?? ''),
                by: SafeHtml::fromTrustedHtml(UserDisplay::username($posterid, false, true, true, false, false, true)),
                authorToggleUrl: $authorid
                    ? '?action=viewtopic&topicid='.$topicid
                    : '?action=viewtopic&topicid='.$topicid.'&authorid='.$posterid,
                authorToggleLabel: (string) ($authorid
                    ? __('legacy/forums.text_view_all_posts')
                    : __('legacy/forums.text_view_this_author_only')),
                avatarImage: SafeHtml::fromTrustedHtml(UserDisplay::avatarImageWithContext(htmlspecialchars($avatar))),
                classImage: UserClass::imagePath((int) ($arr2['class'] ?? 0)),
                className: $className,
                postCount: (int) $forumposts,
                uploaded: Format::size($arr2['uploaded'] ?? 0),
                downloaded: Format::size($arr2['downloaded'] ?? 0),
                ratio: SafeHtml::fromTrustedHtml((string) Ratio::forUserId((int) ($arr2['id'] ?? 0))),
                body: $bodyContent,
                signature: $signature !== ''
                    ? $renderFmt('fmt_sig_'.md5($signature), static fn () => Format::formatComment($signature, false, false, false, true, 500, true, false, 1, 200))
                    : null,
                editedBy: $editedBy,
                editedAtRaw: $editedAtRaw,
                online: ($arr2['last_access'] ?? '') > $dt,
                posterId: (int) ($arr2['id'] ?? 0),
                posterName: (string) ($arr2['username'] ?? ''),
                canQuote: $maypost && $canViewProtected,
                canDelete: $isMod,
                canEdit: ($curUser['id'] == $posterid && ! $locked) || $isMod,
            );
        }

        $moveForums = [];
        if ($isMod) {
            foreach ($this->index->getForumRow(0) ?? [] as $forumRow) {
                if ($forumRow['id'] != $forumid && UserDisplay::currentClass() >= (int) $forumRow['minclasswrite']) {
                    $moveForums[] = ['id' => (int) $forumRow['id'], 'name' => (string) $forumRow['name']];
                }
            }
        }

        return new ViewTopicViewModel(
            topicid: $topicid,
            forumid: $forumid,
            forumname: $forumname,
            sitename: SiteConfig::current()->basic->siteName(),
            subject: $subject,
            locked: $locked,
            sticky: $sticky,
            views: $views,
            mayPost: $maypost,
            isMod: $isMod,
            authorid: $authorid,
            requestUri: (string) Input::serverValue('REQUEST_URI'),
            page: $page,
            pages: $pages,
            posts: $posts,
            moveForums: $moveForums,
            highlightColorOptions: $this->index->highlightColorOptions((string) (__('legacy/forums.select_color'))),
            quickReply: $maypost
                ? SafeHtml::fromTrustedHtml(Html::quickReply('compose', 'body', (string) (__('legacy/forums.submit_add_reply'))))
                : null,
            deniedNotice: ! $maypost
                ? SafeHtml::fromTrustedHtml(view('forums._denied-notice', ['locked' => $locked])->render())
                : null,
            keyScript: SafeHtml::fromTrustedHtml(Html::keyShortcutScript($page, max(0, $pages - 1), (string) $request->attributes->get('csp_nonce', ''))),
        );
    }
}
