<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;
use App\Support\Config\SiteConfig;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\PageResponses;
use App\ViewModels\Forum\ForumComposeViewModel;
use Illuminate\Http\Request;

/**
 * Builds the compose-frame view model (new topic, reply, quote, edit)
 * for the forums page. Rendered by the `x-forum.compose` component.
 */
final class ForumComposeService
{
    public function __construct(
        private readonly ForumRepositoryInterface $forumRepository,
        private readonly TopicRepository $topicRepository,
        private readonly PostLookupRepository $postRepository,
    ) {}

    /**
     * Build the compose-frame view model for the requested type.
     * Returns null for unknown types and for edit targets that no
     * longer exist (callers render an empty section).
     */
    public function buildComposeFrame(int $id, string $type): ?ForumComposeViewModel
    {
        $maxsubjectlength = SiteConfig::current()->main->maxSubjectLength(100);
        $hassubject = false;
        $subject = '';
        $body = '';
        $postid = null;
        $hiddenId = $id;
        $hiddenType = $type;

        switch ($type) {
            case 'new':
                $forumname = $this->forumRepository->getForumName((int) $id) ?? '';
                $title = view('components.title-link', ['before' => __('forums.text_new_topic_in').' ', 'url' => '?action=viewforum&forumid='.$id, 'text' => $forumname, 'after' => ' '.__('forums.text_forum')])->render();
                $hassubject = true;
                break;

            case 'reply':
                $topicname = $this->topicRepository->getTopicSubject((int) $id) ?? '';
                $title = view('components.title-link', ['before' => __('forums.text_reply_to_topic').' ', 'url' => '?action=viewtopic&topicid='.$id, 'text' => $topicname, 'after' => ' '])->render();
                break;

            case 'quote':
                $post = $this->postRepository->getPostForQuote((int) $id);
                if (! $post) {
                    PageResponses::abort(__('forums.std_error'), __('forums.std_no_post_id'));
                }
                $topicid = $post['topicid'];
                $topicname = $post['topic_subject'] ?? '';
                $title = view('components.title-link', ['before' => __('forums.text_reply_to_topic').' ', 'url' => '?action=viewtopic&topicid='.$topicid, 'text' => $topicname, 'after' => ' '])->render();
                $body = '[quote='.(string) $post['username'].']'.(string) $post['body'].'[/quote]';
                $postid = $id;
                $hiddenId = $topicid;
                $hiddenType = 'reply';
                break;

            case 'edit':
                $post = $this->postRepository->getPostForEdit((int) $id);
                if (! $post) {
                    return null;
                }
                if ($post['is_first_post']) {
                    $subject = (string) ($post['topic_subject'] ?? '');
                    $hassubject = true;
                }
                $body = (string) $post['body'];
                $title = __('forums.text_edit_post');
                break;

            default:
                return null;
        }

        return new ForumComposeViewModel(
            titleHtml: SafeHtml::fromTrustedHtml((string) $title),
            hiddenId: (int) $hiddenId,
            hiddenType: $hiddenType,
            postid: $postid,
            hasSubject: $hassubject,
            subject: $subject,
            body: $body,
            maxSubjectLength: $maxsubjectlength,
        );
    }

    public function buildNewTopic(Request $request): ?ForumComposeViewModel
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        $this->checkWhetherExist($forumid, 'forum');

        return $this->buildComposeFrame($forumid, 'new');
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function buildQuotePost(array $curUser, Request $request): ?ForumComposeViewModel
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post');
        if (! Forum::canViewPost((int) ($curUser['id'] ?? 0), $postid)) {
            PageResponses::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'quote');
    }

    public function buildReply(Request $request): ?ForumComposeViewModel
    {
        $topicid = (int) (request()->query('topicid') ?? 0);
        $this->checkWhetherExist($topicid, 'topic');

        return $this->buildComposeFrame($topicid, 'reply');
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function buildEditPost(array $curUser, Request $request): ?ForumComposeViewModel
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post');

        $post = $this->postRepository->getPostWithTopic((int) $postid);
        if (! $post) {
            PageResponses::abort(__('forums.std_error'), __('forums.std_no_post_id'));
        }

        $locked = (bool) $post['locked'];
        $ismod = Forum::isModerator($postid, 'post');
        if (($curUser['id'] != $post['userid'] || $locked) && ! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod) {
            PageResponses::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'edit');
    }

    public function checkWhetherExist(int $id, string $place): void
    {
        PageResponses::assertId($id, true);
        switch ($place) {
            case 'forum':
                if (! $this->forumRepository->forumExists((int) $id)) {
                    PageResponses::abort(__('forums.std_error'), __('forums.std_no_forum_id'));
                }
                break;

            case 'topic':
                $forumid = $this->topicRepository->topicExists((int) $id);
                if (! $forumid) {
                    PageResponses::abort(__('forums.std_error'), __('forums.std_bad_topic_id'));
                }
                $this->checkWhetherExist((int) $forumid, 'forum');
                break;

            case 'post':
                $topicid = $this->postRepository->postExists((int) $id);
                if (! $topicid) {
                    PageResponses::abort(__('forums.std_error'), __('forums.std_no_post_id'));
                }
                $this->checkWhetherExist((int) $topicid, 'topic');
                break;
        }
    }
}
