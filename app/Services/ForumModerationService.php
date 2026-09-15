<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicModerationRepository;
use App\Repositories\TopicRepository;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Globals;
use App\Support\Http\SafeReturnUrl;
use App\Support\LegacyResponse;
use App\Support\Palette;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Forum moderation mutation actions (move, delete, lock, sticky,
 * highlight). Extracted from ForumService to keep both classes under
 * the 400-line ratchet.
 */
final class ForumModerationService
{
    public function __construct(
        private readonly ForumRepositoryInterface $repository,
        private readonly Globals $globals,
        private readonly LegacyRedisCache $cache,
        private readonly TopicPolicy $topicPolicy,
        private readonly PostPolicy $postPolicy,
        private readonly TopicRepository $topicRepository,
        private readonly TopicModerationRepository $topicModerationRepository,
        private readonly PostRepositoryInterface $postRepository,
        private readonly PostLookupRepository $postLookupRepository,
    ) {}

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

    public function moveTopic(Request $request): RedirectResponse
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
        $this->topicModerationRepository->moveTopic($topicid, $forumid, $postCount, (int) $oldForumid);

        if ($oldForumid !== $forumid) {
            $todayDate = date('Y-m-d');
            $this->cacheDelete('forum_'.$oldForumid.'_post_'.$todayDate.'_count');
            $this->cacheDelete('forum_'.$oldForumid.'_last_replied_topic_content');
            $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }

        return $this->redirectTo('?action=viewforum&forumid='.$forumid);
    }

    public function deleteTopic(Request $request): RedirectResponse
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
        $this->topicModerationRepository->deleteTopic($topicid, $forumid, $postCount);

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

    public function deletePost(Request $request): RedirectResponse
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
        $prevPostId = $this->postLookupRepository->getPreviousPostId($topicid, $postid);

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

    public function setLocked(Request $request): RedirectResponse
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
        $this->topicModerationRepository->updateTopicLocked($topicid, $locked);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }

    public function highlightTopic(Request $request): RedirectResponse
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
            $this->topicModerationRepository->updateTopicHighlight($topicid, $color);
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

    public function setSticky(Request $request): RedirectResponse
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

        $sticky = $request->input('sticky');
        $this->topicModerationRepository->updateTopicSticky($topicid, $sticky === 'yes' || $sticky === '1' || $sticky === 1 || $sticky === true);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }
}
