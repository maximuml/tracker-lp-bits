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
use App\Support\Format;
use App\Support\Forum;
use App\Support\Frame;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\LegacyYesNo;
use App\Support\Ratio;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
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
        private readonly Globals $globals,
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
    public function buildViewTopic(array $curUser, int $userId, Request $request, int $postsperpage): ?ViewTopicViewModel
    {
        $highlight = trim((string) ($request->query('highlight') ?? ''));
        $topicid = (int) ($request->query('topicid') ?? 0);
        LegacyResponse::assertId($topicid, true);
        $page = is_string($val = $request->query('page')) ? $val : 0;
        $authorid = (int) ($request->query('authorid') ?? 0);

        $topic = $this->topicRepository->getTopic($topicid);
        if (! $topic) {
            LegacyResponse::abort(__('legacy/forums.std_forum_error'), __('legacy/forums.std_topic_not_found'));

            return null;
        }
        $arr = $topic->toArray();

        $forumid = (int) $arr['forumid'];
        $locked = (bool) $arr['locked'];
        $orgsubject = (string) $arr['subject'];
        $subject = SafeHtml::fromTrustedHtml(
            $highlight !== ''
                ? Format::highlight(htmlspecialchars($highlight), $orgsubject)
                : htmlspecialchars($orgsubject),
        );
        $sticky = $arr['sticky'] == 1;
        $views = (int) $arr['views'];

        $row = $this->index->getForumRow($forumid);
        $forumname = (string) ($row['name'] ?? '');
        $isForummod = Forum::isModerator($forumid, 'forum');
        $isMod = Permission::can(PermissionEnum::POST_MANAGE) || $isForummod;

        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_unpermitted_viewing_topic'));
        }
        $maypost = ((UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && ! $locked) || $isMod) && LegacyYesNo::isYes($curUser['forumpost'] ?? null);

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
            $uidArr[$postArr['userid']] = 1;
        }
        $uidArr = array_keys($uidArr);

        $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
        $userInfoArr = $this->forumRepository->getUsersByIds($uidArr, $neededColumns);
        $lpr = $this->index->getLastReadPostId($topicid, $curUser);

        $posts = [];
        $pn = 0;
        foreach ($allPosts as $arr) {
            $pn++;
            $postid = (int) $arr['id'];
            $posterid = (int) $arr['userid'];

            $userInfo = $userInfoArr->get($posterid) ?: User::defaultUser();
            $arr2 = $userInfo->toArray();

            if (! $forumposts = $this->legacyRedisCache?->get_value('user_'.$posterid.'_post_count')) {
                $forumposts = $this->postRepository->countUserPosts($posterid);
                $this->legacyRedisCache?->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
            }

            $signature = LegacyYesNo::isYes($curUser['signatures'] ?? null) ? (string) ($arr2['signature'] ?? '') : '';
            $avatar = LegacyYesNo::isYes($curUser['avatars'] ?? null) ? (string) ($arr2['avatar'] ?? '') : '';
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
                ? Format::formatComment((string) $arr['body'])
                : Format::formatComment((string) (__('legacy/forums.text_post_protected')));
            if ($highlight !== '') {
                $bodyContent = SafeHtml::fromTrustedHtml(Format::highlight(htmlspecialchars($highlight), (string) $bodyContent));
            }

            $editedBy = null;
            $editedAtRaw = null;
            if (Validators::isId($arr['editedby'])) {
                $editedBy = SafeHtml::fromTrustedHtml(UserDisplay::username((int) $arr['editedby']));
                $editedAtRaw = $arr['editdate'];
            }

            $dt = date('Y-m-d H:i:s', (int) (defined('TIMENOW') ? constant('TIMENOW') : time()) - 900);
            $className = strip_tags((string) UserClass::name((int) ($arr2['class'] ?? 0), false, false, true));

            $posts[] = new PostViewModel(
                id: $postid,
                number: $pn + $offset,
                isLast: $isLast,
                anchorUrl: 'forums.php?action=viewtopic&topicid='.$topicid.'&page=p'.$postid.'#pid'.$postid,
                addedRaw: $arr['added'],
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
                uploaded: Format::size($arr2['uploaded']),
                downloaded: Format::size($arr2['downloaded']),
                ratio: SafeHtml::fromTrustedHtml((string) Ratio::forUserId((int) $arr2['id'])),
                body: $bodyContent,
                signature: $signature !== ''
                    ? SafeHtml::fromTrustedHtml((string) Format::formatComment($signature, false, false, false, true, 500, true, false, 1, 200))
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
            sitename: (string) $this->globals->get('SITENAME', ''),
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
            highlightColorOptions: SafeHtml::fromTrustedHtml($this->index->highlightColorOptions((string) (__('legacy/forums.select_color')))),
            quickReply: $maypost
                ? SafeHtml::fromTrustedHtml(Html::quickReply('compose', 'body', (string) (__('legacy/forums.submit_add_reply'))))
                : null,
            deniedNotice: ! $maypost
                ? SafeHtml::fromUntrustedHtml((string) __(
                    $locked ? 'legacy/forums.text_topic_locked_new_denied' : 'legacy/forums.text_unpermitted_posting_here',
                ))
                : null,
            keyScript: SafeHtml::fromTrustedHtml(Html::keyShortcutScript($page, max(0, $pages - 1), (string) $request->attributes->get('csp_nonce', ''))),
            frameOpen: Frame::open('', false, 10, '100%', 'left'),
            frameClose: Frame::close(),
        );
    }
}
