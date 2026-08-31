<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\ForumRepository;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Palette;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * Handles forum mutation actions (post, move, delete, lock, sticky, highlight).
 */
final class ForumService
{
    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function legacy(Request $request): array|RedirectResponse
    {
        $action = (string) $request->input('action', $request->query('action', ''));

        if ($action === 'post') {
            return $this->handlePost($request);
        }
        if ($action === 'movetopic') {
            return $this->handleMoveTopic($request);
        }
        if ($action === 'deletetopic') {
            return $this->handleDeleteTopic($request);
        }
        if ($action === 'deletepost') {
            return $this->handleDeletePost($request);
        }
        if ($action === 'setlocked') {
            return $this->handleSetLocked($request);
        }
        if ($action === 'hltopic') {
            return $this->handleHighlightTopic($request);
        }
        if ($action === 'setsticky') {
            return $this->handleSetSticky($request);
        }

        // Read-only actions are rendered by ForumPageService in the
        // controller. Signal "handled as read" with an empty array.
        return [];
    }

    public function __construct(
        private readonly ForumRepository $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        return (array) (app(CurrentUser::class)->get() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function lang(): array
    {
        return (array) (app(Globals::class)->get('lang_forums') ?? []);
    }

    private function cacheDelete(string $key): void
    {
        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value($key);
        }
    }

    private function cacheGet(string $key): mixed
    {
        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            return $cache->get_value($key);
        }

        return false;
    }

    private function redirectTo(string $path): RedirectResponse
    {
        if (str_starts_with($path, '?')) {
            return redirect('/forums.php'.$path);
        }

        return redirect($path);
    }

    private function handlePost(Request $request): RedirectResponse
    {
        $user = $this->user();
        $lang = $this->lang();

        if (! ($user['forumpost'] ?? true)) {
            LegacyResponse::abort($lang['std_sorry'] ?? 'Sorry', $lang['std_unauthorized_to_post'] ?? 'Unauthorized.', false);
        }

        $id = (int) $request->input('id');
        $type = (string) $request->input('type');
        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        $hassubject = false;
        $topicid = 0;
        $forumid = 0;
        $postid = 0;
        $quotepostid = (int) $request->input('postid');

        switch ($type) {
            case 'new':
                if (! app(ForumRepository::class)->forumExists($id)) {
                    LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_forum_id'] ?? 'Forum not found.');
                }
                $forumid = $id;
                $hassubject = true;
                break;

            case 'reply':
                $forumid = app(ForumRepository::class)->topicExists($id);
                if ($forumid === null) {
                    LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_topic_id'] ?? 'Topic not found.');
                }
                $topicid = $id;
                break;

            case 'edit':
                $post = app(ForumRepository::class)->getPostEditInfo($id);
                if ($post === null) {
                    return $this->redirectTo('/forums.php');
                }
                $topicid = $post['topicid'];
                $forumid = $post['forumid'];
                $postid = $id;
                $hassubject = (bool) $post['is_first_post'];
                break;

            default:
                return $this->redirectTo('/forums.php');
        }

        if ($hassubject) {
            $subject = trim($subject);
            if ($subject === '') {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_must_enter_subject'] ?? 'Enter subject.');
            }
            $maxsubjectlength = (int) (app(Globals::class)->get('maxsubjectlength') ?? 100);
            if (strlen($subject) > $maxsubjectlength) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_subject_limited'] ?? 'Subject too long.');
            }
        }

        $forumRow = $this->repository->getForumRow($forumid);
        if ($forumRow === null) {
            return $this->redirectTo('/forums.php');
        }

        $userClass = UserDisplay::currentClass();
        if (
            $userClass < (int) ($forumRow['minclassread'] ?? 0)
            || $userClass < (int) ($forumRow['minclasswrite'] ?? 0)
            || ($type === 'new' && $userClass < (int) ($forumRow['minclasscreate'] ?? 0))
        ) {
            LegacyResponse::permissionDenied();
        }

        if ($body === '') {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_body_text'] ?? 'Enter body.');
        }

        $userid = (int) ($user['id'] ?? 0);
        $date = date('Y-m-d H:i:s');

        if ($type !== 'new') {
            $locked = app(ForumRepository::class)->isTopicLocked($topicid);
            if ($locked === null) {
                return $this->redirectTo('/forums.php');
            }
            if (
                $locked
                && ! Permission::can(PermissionEnum::POST_MANAGE)
                && ! Forum::isModerator($topicid, 'topic')
            ) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_topic_locked'] ?? 'Topic locked.');
            }
        }

        if ($type === 'edit') {
            $postInfo = app(ForumRepository::class)->getPostWithUser($postid);
            $topicInfo = app(ForumRepository::class)->getTopicWithUser($topicid);
            if (
                $postInfo === null
                || $topicInfo === null
                || (
                    $postInfo->userid !== $user['id']
                    && ! Forum::isModerator($postid, 'post')
                    && ! Permission::can(PermissionEnum::POST_MANAGE)
                )
            ) {
                LegacyResponse::permissionDenied();
            }

            if ($postInfo === null || $topicInfo === null) {
                return $this->redirectTo('/forums.php');
            }

            if ($hassubject) {
                app(ForumRepository::class)->updateTopicSubject($topicid, $subject);
                $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
                if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
                    $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
                }
            }

            app(ForumRepository::class)->updatePostBody($postid, $body, $date, $userid);
            $this->cacheDelete('post_'.$postid.'_content');

            $postUrl = sprintf('[url=/forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $postid, $postid, $topicInfo->subject ?? '');
            if ($postInfo->userid > 0 && $postInfo->userid !== $userid) {
                $receiver = $postInfo->user;
                if ($receiver !== null) {
                    $locale = $receiver->locale;
                    Message::add([
                        'sender' => null,
                        'receiver' => $receiver->id,
                        'subject' => Locale::trans('forum.post.edited_notify_subject', [], $locale),
                        'msg' => Locale::trans('forum.post.edited_notify_body', ['topic_subject' => $postUrl, 'editor' => $user['username'] ?? ''], $locale),
                        'added' => now(),
                    ]);
                }
            }

            $headerstr = '/forums.php?action=viewtopic&topicid='.$topicid;

            return $this->redirectTo($headerstr.'&page=p'.$postid.'#pid'.$postid);
        }

        if (! Permission::can(PermissionEnum::POST_MANAGE)) {
            $lastPost = $user['last_post'] ?? '1970-01-01 00:00:00';
            $timenow = defined('TIMENOW') ? (int) constant('TIMENOW') : time();
            if (strtotime($lastPost) > ($timenow - 10)) {
                $secs = 10 - ($timenow - strtotime($lastPost));
                LegacyResponse::abort($lang['std_error'] ?? 'Error', ($lang['std_post_flooding'] ?? '').$secs.($lang['std_seconds_before_making'] ?? ''), false);
            }
        }

        if ($type === 'new') {
            $starttopicBonus = (float) (app(Globals::class)->get('starttopic_bonus') ?? 0);
            if ($starttopicBonus > 0) {
                Bonus::updatePoints('+', $starttopicBonus, $userid);
            }

            $topicid = app(ForumRepository::class)->createTopic($userid, $forumid, $subject);
            if ($topicid <= 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_topic_id_returned'] ?? 'Topic creation failed.');
            }
            app(ForumRepository::class)->incrementForumTopicCount($forumid);
            app(ForumRepository::class)->incrementForumPostCount($forumid);
        } else {
            $makepostBonus = (float) (app(Globals::class)->get('makepost_bonus') ?? 0);
            if ($makepostBonus > 0) {
                Bonus::updatePoints('+', $makepostBonus, $userid);
            }
            app(ForumRepository::class)->incrementForumPostCount($forumid);
        }

        $newPostId = app(ForumRepository::class)->createPost($topicid, $userid, $body, $date);
        if ($newPostId <= 0) {
            return $this->redirectTo('/forums.php');
        }

        $topicInfo = app(ForumRepository::class)->getTopicWithUser($topicid);
        $postUrl = sprintf('[url=/forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $newPostId, $newPostId, $topicInfo ? $topicInfo->subject : '');

        if ($type === 'reply') {
            if ($topicInfo !== null && $topicInfo->userid > 0 && $topicInfo->userid !== $userid) {
                $receiver = $topicInfo->user;
                if ($receiver !== null && $receiver->acceptNotification('topic_reply')) {
                    $locale = $receiver->locale;
                    Message::add([
                        'sender' => null,
                        'receiver' => $receiver->id,
                        'subject' => Locale::trans('forum.topic.replied_notify_subject', [], $locale),
                        'msg' => Locale::trans('forum.topic.replied_notify_body', ['topic_subject' => $postUrl], $locale),
                        'added' => now(),
                    ]);
                }
            }

            if ($quotepostid > 0) {
                $quotePostInfo = app(ForumRepository::class)->getPostWithUser($quotepostid);
                if ($quotePostInfo !== null && $quotePostInfo->userid !== $userid) {
                    $receiver = $quotePostInfo->user;
                    if ($receiver !== null && $receiver->acceptNotification('topic_reply')) {
                        $locale = $receiver->locale;
                        Message::add([
                            'sender' => null,
                            'receiver' => $receiver->id,
                            'subject' => Locale::trans('forum.reply.replied_notify_subject', [], $locale),
                            'msg' => Locale::trans('forum.reply.replied_notify_body', ['topic_subject' => $postUrl, 'replyer' => $user['username'] ?? ''], $locale),
                            'added' => now(),
                        ]);
                    }
                }
            }
        }

        $todayDate = date('Y-m-d');
        $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
        $this->cacheDelete('today_'.$todayDate.'_posts_count');
        $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        $this->cacheDelete('topic_'.$topicid.'_post_count');
        $this->cacheDelete('user_'.$userid.'_post_count');

        if ($type === 'new') {
            app(ForumRepository::class)->updateTopicFirstLastPost($topicid, $newPostId);
        } else {
            app(ForumRepository::class)->setTopicLastPost($topicid, $newPostId);
        }

        app(ForumRepository::class)->updateUserLastPost($userid, $date);

        $headerstr = '/forums.php?action=viewtopic&topicid='.$topicid;

        return $this->redirectTo($headerstr.'&page=last#pid'.$newPostId);
    }

    private function handleMoveTopic(Request $request): RedirectResponse
    {
        $lang = $this->lang();
        $forumid = (int) $request->input('forumid');
        $topicid = (int) $request->query('topicid');
        $ismod = Forum::isModerator($topicid, 'topic');

        if (
            ! Validators::isId($forumid)
            || ! Validators::isId($topicid)
            || (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
        ) {
            LegacyResponse::permissionDenied();
        }

        $minclasswrite = app(ForumRepository::class)->getForumMinclasswrite($forumid);
        if ($minclasswrite === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_forum_not_found'] ?? 'Forum not found.');
        }

        if (UserDisplay::currentClass() < $minclasswrite) {
            LegacyResponse::permissionDenied();
        }

        $oldForumid = app(ForumRepository::class)->getTopicForumId($topicid);
        if ($oldForumid === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_topic_not_found'] ?? 'Topic not found.');
        }

        $postCount = app(ForumRepository::class)->countTopicPosts($topicid);
        app(ForumRepository::class)->moveTopic($topicid, $forumid, $postCount, (int) $oldForumid);

        if ($oldForumid !== $forumid) {
            $todayDate = date('Y-m-d');
            $this->cacheDelete('forum_'.$oldForumid.'_post_'.$todayDate.'_count');
            $this->cacheDelete('forum_'.$oldForumid.'_last_replied_topic_content');
            $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }

        return $this->redirectTo('?action=viewforum&forumid='.$forumid);
    }

    private function handleDeleteTopic(Request $request): RedirectResponse
    {
        $user = $this->user();
        $lang = $this->lang();
        $topicid = (int) $request->query('topicid');
        $topic = app(ForumRepository::class)->getTopicForumAndUser($topicid);

        if ($topic === null) {
            return $this->redirectTo('/forums.php');
        }

        $forumid = (int) $topic['forumid'];
        $targetUserid = (int) $topic['userid'];
        $ismod = Forum::isModerator($topicid, 'topic');

        if (
            ! Validators::isId($topicid)
            || (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
        ) {
            LegacyResponse::permissionDenied();
        }

        $sure = (int) $request->query('sure', 0);
        if ($sure !== 1) {
            LegacyResponse::abort($lang['std_delete_topic'] ?? 'Delete topic', ($lang['std_delete_topic_note'] ?? '')."<a class=altlink href=?action=deletetopic&topicid={$topicid}&sure=1>".($lang['std_here_if_sure'] ?? ''), false);
        }

        $postCount = app(ForumRepository::class)->countTopicPosts($topicid);
        app(ForumRepository::class)->deleteTopic($topicid, $forumid, $postCount);

        $todayDate = date('Y-m-d');
        $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }

        $starttopicBonus = (float) (app(Globals::class)->get('starttopic_bonus') ?? 0);
        if ($starttopicBonus > 0) {
            Bonus::updatePoints('-', $starttopicBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewforum&forumid='.$forumid);
    }

    private function handleDeletePost(Request $request): RedirectResponse
    {
        $user = $this->user();
        $lang = $this->lang();
        $postid = (int) $request->query('postid');
        $sure = (int) $request->query('sure', 0);
        $ismod = Forum::isModerator($postid, 'post');

        if (
            (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
            || ! Validators::isId($postid)
        ) {
            LegacyResponse::permissionDenied();
        }

        $post = app(ForumRepository::class)->getPostTopicAndUser($postid);
        if ($post === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_post_not_found'] ?? 'Post not found.');
        }
        if ($post === null) {
            throw new LogicException('Expected non-null post.');
        }

        $topicid = $post['topicid'];
        $targetUserid = $post['userid'];
        $prevPostId = app(ForumRepository::class)->getPreviousPostId($topicid, $postid);

        if ($prevPostId === null || $prevPostId === 0) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', ($lang['std_cannot_delete_post'] ?? '')."<a class=altlink href=?action=deletetopic&topicid={$topicid}&sure=1>".($lang['std_delete_topic_instead'] ?? ''), false);
        }

        if ($sure !== 1) {
            LegacyResponse::abort($lang['std_delete_post'] ?? 'Delete post', ($lang['std_delete_post_note'] ?? '')."<a class=altlink href=?action=deletepost&postid={$postid}&sure=1>".($lang['std_here_if_sure'] ?? ''), false);
        }

        $redirtopost = '&page=p'.$prevPostId.'#pid'.$prevPostId;
        $forumid = app(ForumRepository::class)->getTopicForumId($topicid) ?? 0;
        if ($forumid === 0) {
            return $this->redirectTo('/forums.php');
        }

        app(ForumRepository::class)->deletePost($postid, $topicid, $forumid);
        $this->cacheDelete('user_'.$targetUserid.'_post_count');
        $this->cacheDelete('topic_'.$topicid.'_post_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['lastpost'] ?? null) == $postid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }
        app(ForumRepository::class)->updateTopicLastPost($topicid);

        $makepostBonus = (float) (app(Globals::class)->get('makepost_bonus') ?? 0);
        if ($makepostBonus > 0) {
            Bonus::updatePoints('-', $makepostBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewtopic&topicid='.$topicid.$redirtopost);
    }

    private function handleSetLocked(Request $request): RedirectResponse
    {
        $topicid = (int) $request->input('topicid');
        $ismod = Forum::isModerator($topicid, 'topic');

        if (
            ! $topicid
            || (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
        ) {
            LegacyResponse::permissionDenied();
        }

        $locked = (bool) $request->input('locked');
        app(ForumRepository::class)->updateTopicLocked($topicid, $locked);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }

    private function handleHighlightTopic(Request $request): RedirectResponse
    {
        $topicid = (int) $request->query('topicid');
        $ismod = Forum::isModerator($topicid, 'topic');

        if (
            ! $topicid
            || (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
        ) {
            LegacyResponse::permissionDenied();
        }

        $color = (int) $request->input('color');
        if ($color === 0 || Palette::forumHighlight($color)) {
            app(ForumRepository::class)->updateTopicHighlight($topicid, $color);
        }

        $forumid = app(ForumRepository::class)->getTopicForumId($topicid) ?? 0;
        if ($forumid > 0) {
            $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
            if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
                $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
            }
        }

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }

    private function handleSetSticky(Request $request): RedirectResponse
    {
        $topicid = (int) $request->input('topicid');
        $ismod = Forum::isModerator($topicid, 'topic');

        if (
            ! $topicid
            || (! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod)
        ) {
            LegacyResponse::permissionDenied();
        }

        $sticky = (string) $request->input('sticky');
        app(ForumRepository::class)->updateTopicSticky($topicid, $sticky);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }
}
