<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Repositories\TopicRepository;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Http\SafeReturnUrl;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Palette;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        private readonly ForumRepositoryInterface $repository,
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly LegacyRedisCache $cache,
        private readonly TopicPolicy $topicPolicy,
        private readonly PostPolicy $postPolicy,
        private readonly TopicRepository $topicRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function user(): array
    {
        return (array) ($this->currentUser->get() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function lang(): array
    {
        return (array) ($this->globals->get('lang_forums') ?? []);
    }

    private function cacheDelete(string $key): void
    {
        $this->cache->delete_value($key);
    }

    private function cacheGet(string $key): mixed
    {
        return $this->cache->get_value($key);
    }

    private function redirectTo(string $path): RedirectResponse
    {
        if (str_starts_with($path, '?')) {
            return redirect('/forums.php'.$path);
        }

        return redirect(SafeReturnUrl::filter($path, '/forums.php'));
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
                if (! $this->repository->forumExists($id)) {
                    LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_forum_id'] ?? 'Forum not found.');
                }
                $forumid = $id;
                $hassubject = true;
                break;

            case 'reply':
                $forumid = $this->topicRepository->topicExists($id);
                if ($forumid === null) {
                    LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_topic_id'] ?? 'Topic not found.');
                }
                $topicid = $id;
                break;

            case 'edit':
                $post = $this->postRepository->getPostEditInfo($id);
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
            $maxsubjectlength = (int) ($this->globals->get('maxsubjectlength') ?? 100);
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
            $topicModel = Topic::query()->whereKey($topicid)->first();
            if ($topicModel === null) {
                return $this->redirectTo('/forums.php');
            }

            // W1-04: Use TopicPolicy for locked-topic reply authorization
            $authUser = Auth::user();
            if ($topicModel->locked && (! $authUser instanceof User || ! $this->topicPolicy->reply($authUser, $topicModel))) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_topic_locked'] ?? 'Topic locked.');
                throw new LogicException('Expected authenticated user.');
            }
        }

        if ($type === 'edit') {
            $postInfo = $this->postRepository->getPostWithUser($postid);
            $topicInfo = $this->topicRepository->getTopicWithUser($topicid);
            if ($postInfo === null || $topicInfo === null) {
                return $this->redirectTo('/forums.php');
            }

            // W1-04: Use PostPolicy for edit authorization
            $postModel = Post::query()->whereKey($postid)->first();
            $authUser = Auth::user();
            if (! $authUser instanceof User || $postModel === null || ! $this->postPolicy->update($authUser, $postModel)) {
                LegacyResponse::permissionDenied();
                throw new LogicException('Expected authenticated user and non-null post.');
            }

            if ($hassubject) {
                $this->topicRepository->updateTopicSubject($topicid, $subject);
                $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
                if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
                    $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
                }
            }

            $this->postRepository->updatePostBody($postid, $body, $date, $userid);
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
            $starttopicBonus = (float) ($this->globals->get('starttopic_bonus') ?? 0);
            if ($starttopicBonus > 0) {
                Bonus::updatePoints('+', $starttopicBonus, $userid);
            }

            $topicid = $this->topicRepository->createTopic($userid, $forumid, $subject);
            if ($topicid <= 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_topic_id_returned'] ?? 'Topic creation failed.');
            }
            $this->repository->incrementForumTopicCount($forumid);
            $this->repository->incrementForumPostCount($forumid);
        } else {
            $makepostBonus = (float) ($this->globals->get('makepost_bonus') ?? 0);
            if ($makepostBonus > 0) {
                Bonus::updatePoints('+', $makepostBonus, $userid);
            }
            $this->repository->incrementForumPostCount($forumid);
        }

        $newPostId = $this->postRepository->createPost($topicid, $userid, $body, $date);
        if ($newPostId <= 0) {
            return $this->redirectTo('/forums.php');
        }

        $topicInfo = $this->topicRepository->getTopicWithUser($topicid);
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
                $quotePostInfo = $this->postRepository->getPostWithUser($quotepostid);
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
            $this->topicRepository->updateTopicFirstLastPost($topicid, $newPostId);
        } else {
            $this->topicRepository->setTopicLastPost($topicid, $newPostId);
        }

        $this->postRepository->updateUserLastPost($userid, $date);

        $headerstr = '/forums.php?action=viewtopic&topicid='.$topicid;

        return $this->redirectTo($headerstr.'&page=last#pid'.$newPostId);
    }

    private function handleMoveTopic(Request $request): RedirectResponse
    {
        $lang = $this->lang();
        $forumid = (int) $request->input('forumid');
        $topicid = (int) $request->query('topicid');

        $topic = Topic::query()->whereKey($topicid)->first();
        if ($topic === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_topic_not_found'] ?? 'Topic not found.');
            throw new LogicException('Expected non-null topic.');
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->move($user, $topic)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $minclasswrite = $this->repository->getForumMinclasswrite($forumid);
        if ($minclasswrite === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_forum_not_found'] ?? 'Forum not found.');
        }

        if (UserDisplay::currentClass() < $minclasswrite) {
            LegacyResponse::permissionDenied();
        }

        $oldForumid = $this->topicRepository->getTopicForumId($topicid);
        if ($oldForumid === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_topic_not_found'] ?? 'Topic not found.');
        }

        $postCount = $this->postRepository->countTopicPosts($topicid);
        $this->topicRepository->moveTopic($topicid, $forumid, $postCount, (int) $oldForumid);

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
        $lang = $this->lang();
        $topicid = (int) $request->query('topicid');
        $topic = Topic::query()->whereKey($topicid)->first();

        if ($topic === null) {
            return $this->redirectTo('/forums.php');
        }

        $forumid = (int) $topic->forumid;
        $targetUserid = (int) $topic->userid;

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->delete($user, $topic)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $sure = (int) $request->query('sure', 0);
        if ($sure !== 1) {
            LegacyResponse::abort($lang['std_delete_topic'] ?? 'Delete topic', ($lang['std_delete_topic_note'] ?? '')."<a class=altlink href=?action=deletetopic&topicid={$topicid}&sure=1>".($lang['std_here_if_sure'] ?? ''), false);
        }

        $postCount = $this->postRepository->countTopicPosts($topicid);
        $this->topicRepository->deleteTopic($topicid, $forumid, $postCount);

        $todayDate = date('Y-m-d');
        $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }

        $starttopicBonus = (float) ($this->globals->get('starttopic_bonus') ?? 0);
        if ($starttopicBonus > 0) {
            Bonus::updatePoints('-', $starttopicBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewforum&forumid='.$forumid);
    }

    private function handleDeletePost(Request $request): RedirectResponse
    {
        $lang = $this->lang();
        $postid = (int) $request->query('postid');
        $sure = (int) $request->query('sure', 0);

        $post = Post::query()->whereKey($postid)->first();
        if ($post === null) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_post_not_found'] ?? 'Post not found.');
            throw new LogicException('Expected non-null post.');
        }

        // W1-04: Use PostPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->postPolicy->delete($user, $post)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $topicid = (int) $post->topicid;
        $targetUserid = (int) $post->userid;
        $prevPostId = $this->postRepository->getPreviousPostId($topicid, $postid);

        if ($prevPostId === null || $prevPostId === 0) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', ($lang['std_cannot_delete_post'] ?? '')."<a class=altlink href=?action=deletetopic&topicid={$topicid}&sure=1>".($lang['std_delete_topic_instead'] ?? ''), false);
        }

        if ($sure !== 1) {
            LegacyResponse::abort($lang['std_delete_post'] ?? 'Delete post', ($lang['std_delete_post_note'] ?? '')."<a class=altlink href=?action=deletepost&postid={$postid}&sure=1>".($lang['std_here_if_sure'] ?? ''), false);
        }

        $redirtopost = '&page=p'.$prevPostId.'#pid'.$prevPostId;
        $forumid = $this->topicRepository->getTopicForumId($topicid) ?? 0;
        if ($forumid === 0) {
            return $this->redirectTo('/forums.php');
        }

        $this->postRepository->deletePost($postid, $topicid, $forumid);
        $this->cacheDelete('user_'.$targetUserid.'_post_count');
        $this->cacheDelete('topic_'.$topicid.'_post_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['lastpost'] ?? null) == $postid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }
        $this->topicRepository->updateTopicLastPost($topicid);

        $makepostBonus = (float) ($this->globals->get('makepost_bonus') ?? 0);
        if ($makepostBonus > 0) {
            Bonus::updatePoints('-', $makepostBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewtopic&topicid='.$topicid.$redirtopost);
    }

    private function handleSetLocked(Request $request): RedirectResponse
    {
        $topicid = (int) $request->input('topicid');
        $topic = Topic::query()->whereKey($topicid)->first();

        if ($topic === null) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected non-null topic.');
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->lock($user, $topic)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $locked = (bool) $request->input('locked');
        $this->topicRepository->updateTopicLocked($topicid, $locked);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }

    private function handleHighlightTopic(Request $request): RedirectResponse
    {
        $topicid = (int) $request->query('topicid');
        $topic = Topic::query()->whereKey($topicid)->first();

        if ($topic === null) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected non-null topic.');
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->highlight($user, $topic)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $color = (int) $request->input('color');
        if ($color === 0 || Palette::forumHighlight($color)) {
            $this->topicRepository->updateTopicHighlight($topicid, $color);
        }

        $forumid = $this->topicRepository->getTopicForumId($topicid) ?? 0;
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
        $topic = Topic::query()->whereKey($topicid)->first();

        if ($topic === null) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected non-null topic.');
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->sticky($user, $topic)) {
            LegacyResponse::permissionDenied();
            throw new LogicException('Expected authenticated user.');
        }

        $sticky = (string) $request->input('sticky');
        $this->topicRepository->updateTopicSticky($topicid, $sticky);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }
}
