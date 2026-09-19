<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
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
        private readonly Globals $globals,
    ) {}

    /**
     * Build the compose-frame view model for the requested type.
     * Returns null for unknown types and for edit targets that no
     * longer exist (callers render an empty section).
     */
    public function buildComposeFrame(int $id, string $type): ?ForumComposeViewModel
    {
        $maxsubjectlength = (int) $this->globals->get('maxsubjectlength');
        $hassubject = false;
        $subject = '';
        $body = '';
        $postid = null;
        $hiddenId = $id;
        $hiddenType = $type;

        switch ($type) {
            case 'new':
                $forumname = $this->forumRepository->getForumName((int) $id) ?? '';
                $title = (__('legacy/forums.text_new_topic_in')).' <a href="'.htmlspecialchars('?action=viewforum&forumid='.$id).'">'.htmlspecialchars($forumname).'</a> '.(__('legacy/forums.text_forum'));
                $hassubject = true;
                break;

            case 'reply':
                $topicname = $this->topicRepository->getTopicSubject((int) $id) ?? '';
                $title = (__('legacy/forums.text_reply_to_topic')).' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$id).'">'.htmlspecialchars($topicname).'</a> ';
                break;

            case 'quote':
                $post = $this->postRepository->getPostForQuote((int) $id);
                if (! $post) {
                    LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_post_id'));

                    return null;
                }
                $topicid = $post['topicid'];
                $topicname = $post['topic_subject'] ?? '';
                $title = (__('legacy/forums.text_reply_to_topic')).' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid).'">'.htmlspecialchars($topicname).'</a> ';
                $body = '[quote='.Input::unescape((string) $post['username']).']'.Input::unescape((string) $post['body']).'[/quote]';
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
                $body = Input::unescape((string) $post['body']);
                $title = __('legacy/forums.text_edit_post');
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
            LegacyResponse::permissionDenied();
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
            LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_post_id'));

            return null;
        }

        $locked = (bool) $post['locked'];
        $ismod = Forum::isModerator($postid, 'post');
        if (($curUser['id'] != $post['userid'] || $locked) && ! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod) {
            LegacyResponse::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'edit');
    }

    public function checkWhetherExist(int $id, string $place): void
    {
        LegacyResponse::assertId($id, true);
        switch ($place) {
            case 'forum':
                if (! $this->forumRepository->forumExists((int) $id)) {
                    LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_forum_id'));
                }
                break;

            case 'topic':
                $forumid = $this->topicRepository->topicExists((int) $id);
                if (! $forumid) {
                    LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_bad_topic_id'));
                }
                $this->checkWhetherExist((int) $forumid, 'forum');
                break;

            case 'post':
                $topicid = $this->postRepository->postExists((int) $id);
                if (! $topicid) {
                    LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_post_id'));
                }
                $this->checkWhetherExist((int) $topicid, 'topic');
                break;
        }
    }
}
