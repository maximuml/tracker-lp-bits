<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Repositories\TopicModerationRepository;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Http\SafeReturnUrl;
use App\Support\LegacyResponse;
use App\Support\Palette;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Forum moderation mutation actions (move, delete, lock, sticky,
 * highlight). Extracted from ForumService to keep both classes under
 * the 400-line ratchet.
 */
final class ForumModerationService
{
    public function __construct(
        private readonly ForumDataRepositories $data,
        private readonly LegacyRedisCache $cache,
        private readonly TopicPolicy $topicPolicy,
        private readonly PostPolicy $postPolicy,
        private readonly TopicModerationRepository $topicModerationRepository,
    ) {}

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
            return redirect('/forums'.$path);
        }

        return redirect(SafeReturnUrl::filter($path, '/forums'));
    }

    public function moveTopic(Request $request): RedirectResponse
    {
        $forumid = (int) $request->input('forumid');
        $topicid = (int) $request->query('topicid');

        $topic = $this->data->topics->getTopic((int) $topicid);
        if ($topic === null) {
            LegacyResponse::abort(__('forums.std_error'), __('forums.std_topic_not_found'));
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->move($user, $topic)) {
            LegacyResponse::permissionDenied();
        }

        $minclasswrite = $this->data->forums->getForumMinclasswrite($forumid);
        if ($minclasswrite === null) {
            LegacyResponse::abort(__('forums.std_error'), __('forums.std_forum_not_found'));
        }

        if (UserDisplay::currentClass() < $minclasswrite) {
            LegacyResponse::permissionDenied();
        }

        $oldForumid = $this->data->topics->getTopicForumId($topicid);
        if ($oldForumid === null) {
            LegacyResponse::abort(__('forums.std_error'), __('forums.std_topic_not_found'));
        }

        $postCount = $this->data->posts->countTopicPosts($topicid);
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
        $topicid = (int) $request->input('topicid');
        $topic = $this->data->topics->getTopic((int) $topicid);

        if ($topic === null) {
            return $this->redirectTo('/forums');
        }

        $forumid = (int) $topic->forumid;
        $targetUserid = (int) $topic->userid;

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->delete($user, $topic)) {
            LegacyResponse::permissionDenied();
        }

        $sure = (int) $request->input('sure', 0);
        if ($sure !== 1 || ! $request->isMethod('POST')) {
            LegacyResponse::abort(__('forums.std_delete_topic'), (__('forums.std_delete_topic_note')).view('forums._confirm-form', ['action' => 'deletetopic', 'name' => 'topicid', 'value' => $topicid, 'text' => __('forums.std_here')])->render().__('forums.std_if_sure'), false);
        }

        $postCount = $this->data->posts->countTopicPosts($topicid);
        $this->topicModerationRepository->deleteTopic($topicid, $forumid, $postCount);

        $todayDate = date('Y-m-d');
        $this->cacheDelete('forum_'.$forumid.'_post_'.$todayDate.'_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['id'] ?? null) == $topicid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }

        $starttopicBonus = SiteConfig::current()->bonus->startTopic();
        if ($starttopicBonus > 0) {
            Bonus::updatePoints('-', $starttopicBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewforum&forumid='.$forumid);
    }

    public function deletePost(Request $request): RedirectResponse
    {
        $postid = (int) $request->input('postid');
        $sure = (int) $request->input('sure', 0);

        $post = $this->data->postLookup->getPost((int) $postid);
        if ($post === null) {
            LegacyResponse::abort(__('forums.std_error'), __('forums.std_post_not_found'));
        }

        // W1-04: Use PostPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->postPolicy->delete($user, $post)) {
            LegacyResponse::permissionDenied();
        }

        $topicid = (int) $post->topicid;
        $targetUserid = (int) $post->userid;
        $prevPostId = $this->data->postLookup->getPreviousPostId($topicid, $postid);

        if ($prevPostId === null || $prevPostId === 0) {
            LegacyResponse::abort(__('forums.std_error'), (__('forums.std_cannot_delete_post')).view('components.altlink', ['class' => 'altlink', 'url' => "?action=deletetopic&topicid={$topicid}&sure=1", 'text' => __('forums.std_delete_topic_link')])->render().__('forums.std_instead'), false);
        }

        if ($sure !== 1 || ! $request->isMethod('POST')) {
            LegacyResponse::abort(__('forums.std_delete_post'), (__('forums.std_delete_post_note')).view('forums._confirm-form', ['action' => 'deletepost', 'name' => 'postid', 'value' => $postid, 'text' => __('forums.std_here')])->render().__('forums.std_if_sure'), false);
        }

        $redirtopost = '&page=p'.$prevPostId.'#pid'.$prevPostId;
        $forumid = $this->data->topics->getTopicForumId($topicid) ?? 0;
        if ($forumid === 0) {
            return $this->redirectTo('/forums');
        }

        $this->data->posts->deletePost($postid, $topicid, $forumid);
        $this->cacheDelete('user_'.$targetUserid.'_post_count');
        $this->cacheDelete('topic_'.$topicid.'_post_count');
        $cached = $this->cacheGet('forum_'.$forumid.'_last_replied_topic_content');
        if (is_array($cached) && ($cached['lastpost'] ?? null) == $postid) {
            $this->cacheDelete('forum_'.$forumid.'_last_replied_topic_content');
        }
        $this->data->topics->updateTopicLastPost($topicid);

        $makepostBonus = SiteConfig::current()->bonus->makePost();
        if ($makepostBonus > 0) {
            Bonus::updatePoints('-', $makepostBonus, $targetUserid);
        }

        return $this->redirectTo('?action=viewtopic&topicid='.$topicid.$redirtopost);
    }

    public function setLocked(Request $request): RedirectResponse
    {
        $topicid = (int) $request->input('topicid');
        $topic = $this->data->topics->getTopic((int) $topicid);

        if ($topic === null) {
            LegacyResponse::permissionDenied();
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->lock($user, $topic)) {
            LegacyResponse::permissionDenied();
        }

        $locked = (bool) $request->input('locked');
        $this->topicModerationRepository->updateTopicLocked($topicid, $locked);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }

    public function highlightTopic(Request $request): RedirectResponse
    {
        $topicid = (int) $request->query('topicid');
        $topic = $this->data->topics->getTopic((int) $topicid);

        if ($topic === null) {
            LegacyResponse::permissionDenied();
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->highlight($user, $topic)) {
            LegacyResponse::permissionDenied();
        }

        $color = (int) $request->input('color');
        if ($color === 0 || Palette::forumHighlight($color)) {
            $this->topicModerationRepository->updateTopicHighlight($topicid, $color);
        }

        $forumid = $this->data->topics->getTopicForumId($topicid) ?? 0;
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
        $topic = $this->data->topics->getTopic((int) $topicid);

        if ($topic === null) {
            LegacyResponse::permissionDenied();
        }

        // W1-04: Use TopicPolicy for authorization
        $user = Auth::user();
        if (! $user instanceof User || ! $this->topicPolicy->sticky($user, $topic)) {
            LegacyResponse::permissionDenied();
        }

        $sticky = $request->input('sticky');
        $this->topicModerationRepository->updateTopicSticky($topicid, $sticky === 'yes' || $sticky === '1' || $sticky === 1 || $sticky === true);

        return $this->redirectTo((string) $request->input('returnto', '?action=viewforum'));
    }
}
