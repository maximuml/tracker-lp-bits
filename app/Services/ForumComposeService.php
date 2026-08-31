<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\ForumRepository;
use App\Support\CurrentUser;
use App\Support\Forum;
use App\Support\Frame;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use Illuminate\Http\Request;

/**
 * Builds the compose-frame sections (new topic, reply, quote, edit)
 * for the forums page.
 */
final class ForumComposeService
{
    /**
     * Build the compose-frame HTML for the requested type.
     *
     * @param  array<string, mixed>  $lang
     * @return array{title: string, body: string}
     */
    public function buildComposeFrame(int $id, string $type, array $lang): array
    {
        $maxsubjectlength = (int) app(Globals::class)->get('maxsubjectlength');
        $CURUSER = (array) (app(CurrentUser::class)->get() ?? []);
        $hassubject = false;
        $subject = '';
        $body = '';
        $hiddenId = $id;
        $hiddenType = $type;

        ob_start();
        echo "<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n";
        switch ($type) {
            case 'new':
                $forumname = app(ForumRepository::class)->getForumName((int) $id) ?? '';
                $title = ($lang['text_new_topic_in'] ?? '').' <a href="'.htmlspecialchars('?action=viewforum&forumid='.$id).'">'.htmlspecialchars($forumname).'</a> '.($lang['text_forum'] ?? '');
                $hassubject = true;
                break;

            case 'reply':
                $topicname = app(ForumRepository::class)->getTopicSubject((int) $id) ?? '';
                $title = ($lang['text_reply_to_topic'] ?? '').' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$id).'">'.htmlspecialchars($topicname).'</a> ';
                break;

            case 'quote':
                $post = app(ForumRepository::class)->getPostForQuote((int) $id);
                if (! $post) {
                    ob_get_clean();
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_post_id'] ?? '');

                    return ['title' => '', 'body' => ''];
                }
                $topicid = $post['topicid'];
                $topicname = $post['topic_subject'] ?? '';
                $title = ($lang['text_reply_to_topic'] ?? '').' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid).'">'.htmlspecialchars($topicname).'</a> ';
                $body = '[quote='.htmlspecialchars($post['username']).']'.htmlspecialchars(Input::unescape($post['body'])).'[/quote]';
                echo '<input type="hidden" name="postid" value="'.$id.'" />';
                $hiddenId = $topicid;
                $hiddenType = 'reply';
                break;

            case 'edit':
                $post = app(ForumRepository::class)->getPostForEdit((int) $id);
                if (! $post) {
                    ob_get_clean();

                    return ['title' => '', 'body' => ''];
                }
                $topicid = $post['topicid'];
                if ($post['is_first_post']) {
                    $subject = $post['topic_subject'] ?? '';
                    $hassubject = true;
                }
                $body = htmlspecialchars(Input::unescape($post['body']));
                $title = $lang['text_edit_post'] ?? '';
                break;

            default:
                ob_get_clean();

                return ['title' => '', 'body' => ''];
        }
        echo '<input type="hidden" name="id" value="'.$hiddenId.'" />';
        echo '<input type="hidden" name="type" value="'.$hiddenType.'" />';
        Frame::composeBeginVoid($title, $hiddenType, $body, $hassubject, $subject);
        Frame::composeEndVoid();
        echo '</form>';

        return ['title' => (string) $title, 'body' => (string) ob_get_clean()];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array{title: string, body: string}
     */
    public function buildNewTopic(array $lang, Request $request): array
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        $this->checkWhetherExist($forumid, 'forum', $lang);

        return $this->buildComposeFrame($forumid, 'new', $lang);
    }

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array{title: string, body: string}
     */
    public function buildQuotePost(array $lang, array $curUser, Request $request): array
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post', $lang);
        if (! Forum::canViewPost((int) ($curUser['id'] ?? 0), $postid)) {
            LegacyResponse::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'quote', $lang);
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array{title: string, body: string}
     */
    public function buildReply(array $lang, Request $request): array
    {
        $topicid = (int) (request()->query('topicid') ?? 0);
        $this->checkWhetherExist($topicid, 'topic', $lang);

        return $this->buildComposeFrame($topicid, 'reply', $lang);
    }

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array{title: string, body: string}
     */
    public function buildEditPost(array $lang, array $curUser, Request $request): array
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post', $lang);

        $post = app(ForumRepository::class)->getPostWithTopic((int) $postid);
        if (! $post) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_post_id'] ?? '');

            return ['title' => '', 'body' => ''];
        }

        $locked = (bool) $post['locked'];
        $ismod = Forum::isModerator($postid, 'post');
        if (($curUser['id'] != $post['userid'] || $locked) && ! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod) {
            LegacyResponse::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'edit', $lang);
    }

    /**
     * @param  array<string, mixed>  $lang
     */
    public function checkWhetherExist(int $id, string $place, array $lang): void
    {
        LegacyResponse::assertId($id, true);
        switch ($place) {
            case 'forum':
                if (! app(ForumRepository::class)->forumExists((int) $id)) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_forum_id'] ?? '');
                }
                break;

            case 'topic':
                $forumid = app(ForumRepository::class)->topicExists((int) $id);
                if (! $forumid) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_bad_topic_id'] ?? '');
                }
                $this->checkWhetherExist((int) $forumid, 'forum', $lang);
                break;

            case 'post':
                $topicid = app(ForumRepository::class)->postExists((int) $id);
                if (! $topicid) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_post_id'] ?? '');
                }
                $this->checkWhetherExist((int) $topicid, 'topic', $lang);
                break;
        }
    }
}
