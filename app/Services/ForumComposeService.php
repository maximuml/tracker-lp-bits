<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Frame;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * Builds the compose-frame sections (new topic, reply, quote, edit)
 * for the forums page.
 */
final class ForumComposeService
{
    public function __construct(
        private readonly ForumRepositoryInterface $forumRepository,
        private readonly TopicRepository $topicRepository,
        private readonly PostLookupRepository $postRepository,
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
    ) {}

    /**
     * Build the compose-frame HTML for the requested type.
     *
     * @return array{title: string, body: SafeHtml}
     */
    public function buildComposeFrame(int $id, string $type): array
    {
        $maxsubjectlength = (int) $this->globals->get('maxsubjectlength');
        $CURUSER = (array) ($this->currentUser->get() ?? []);
        $hassubject = false;
        $subject = '';
        $body = '';
        $hiddenId = $id;
        $hiddenType = $type;

        ob_start();
        echo "<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n";
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
                    ob_get_clean();
                    LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_post_id'));

                    return ['title' => '', 'body' => SafeHtml::fromTrustedHtml('')];
                }
                $topicid = $post['topicid'];
                $topicname = $post['topic_subject'] ?? '';
                $title = (__('legacy/forums.text_reply_to_topic')).' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid).'">'.htmlspecialchars($topicname).'</a> ';
                $body = '[quote='.htmlspecialchars($post['username']).']'.htmlspecialchars(Input::unescape($post['body'])).'[/quote]';
                echo '<input type="hidden" name="postid" value="'.$id.'" />';
                $hiddenId = $topicid;
                $hiddenType = 'reply';
                break;

            case 'edit':
                $post = $this->postRepository->getPostForEdit((int) $id);
                if (! $post) {
                    ob_get_clean();

                    return ['title' => '', 'body' => SafeHtml::fromTrustedHtml('')];
                }
                $topicid = $post['topicid'];
                if ($post['is_first_post']) {
                    $subject = $post['topic_subject'] ?? '';
                    $hassubject = true;
                }
                $body = htmlspecialchars(Input::unescape($post['body']));
                $title = __('legacy/forums.text_edit_post');
                break;

            default:
                ob_get_clean();

                return ['title' => '', 'body' => SafeHtml::fromTrustedHtml('')];
        }
        echo '<input type="hidden" name="id" value="'.$hiddenId.'" />'.
            '<input type="hidden" name="type" value="'.$hiddenType.'" />'.
            Frame::composeBegin(new HtmlString((string) $title), $hiddenType, $body, $hassubject, $subject, 100).
            Frame::composeEnd().
            '</form>';

        return ['title' => (string) $title, 'body' => SafeHtml::fromTrustedHtml((string) ob_get_clean())];
    }

    /**
     * @return array{title: string, body: SafeHtml}
     */
    public function buildNewTopic(Request $request): array
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        $this->checkWhetherExist($forumid, 'forum');

        return $this->buildComposeFrame($forumid, 'new');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array{title: string, body: SafeHtml}
     */
    public function buildQuotePost(array $curUser, Request $request): array
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post');
        if (! Forum::canViewPost((int) ($curUser['id'] ?? 0), $postid)) {
            LegacyResponse::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'quote');
    }

    /**
     * @return array{title: string, body: SafeHtml}
     */
    public function buildReply(Request $request): array
    {
        $topicid = (int) (request()->query('topicid') ?? 0);
        $this->checkWhetherExist($topicid, 'topic');

        return $this->buildComposeFrame($topicid, 'reply');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array{title: string, body: SafeHtml}
     */
    public function buildEditPost(array $curUser, Request $request): array
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post');

        $post = $this->postRepository->getPostWithTopic((int) $postid);
        if (! $post) {
            LegacyResponse::abort(__('legacy/forums.std_error'), __('legacy/forums.std_no_post_id'));

            return ['title' => '', 'body' => SafeHtml::fromTrustedHtml('')];
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
