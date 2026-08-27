<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Frame;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\Palette;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\Request;

/**
 * Prepares section data for the forums page, replacing the legacy
 * forum_forums_content.php partial with typed Blade-rendered sections.
 *
 * Sections (action-dispatched):
 *  - newtopic / reply / quotepost / editpost: compose frame form
 *  - viewtopic:  single topic with paginated posts + mod toolbox
 *  - viewforum:  forum view with topic list, sort + search
 *  - viewunread: list of topics with unread posts
 *  - search:     forum keyword search form + results
 *  - forums:     default forum index (overforums + forums list + stats)
 */
final class ForumPageService
{
    /**
     * Build the data for the requested action.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (app(CurrentUser::class)->get() ?? []);
        $lang = (array) (app(Globals::class)->get('lang_forums') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        // Global variables previously set by the procedural partial.
        $maxsubjectlength = 100;
        $postsperpage = (int) ($curUser['postsperpage'] ?? 0);
        if (! $postsperpage) {
            $forumpostsperpage = app(Globals::class)->get('forumpostsperpage');
            if (is_numeric($forumpostsperpage)) {
                $postsperpage = (int) $forumpostsperpage;
            } else {
                $postsperpage = 10;
            }
        }
        $topicsperpage = (int) ($curUser['topicsperpage'] ?? 0);
        if (! $topicsperpage) {
            $forumtopicsperpageMain = app(Globals::class)->get('forumtopicsperpage_main');
            if (is_numeric($forumtopicsperpageMain)) {
                $topicsperpage = (int) $forumtopicsperpageMain;
            } else {
                $topicsperpage = 20;
            }
        }
        $todayDate = date('Y-m-d');
        app(Globals::class)->set('maxsubjectlength', $maxsubjectlength);
        app(Globals::class)->set('postsperpage', $postsperpage);
        app(Globals::class)->set('topicsperpage', $topicsperpage);
        app(Globals::class)->set('today_date', $todayDate);

        $action = htmlspecialchars(trim((string) request()->query('action')));

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'sitename' => (string) app(Globals::class)->get('SITENAME', ''),
            'postsperpage' => $postsperpage,
            'topicsperpage' => $topicsperpage,
            'todayDate' => $todayDate,
        ];

        // catchup is a query-flag action, not a dispatched section.
        if (((request()->query('catchup') !== null)) && request()->query('catchup') == 1) {
            $this->catchUp();
        }

        switch ($action) {
            case 'newtopic':
                $data['compose'] = $this->buildNewTopic($lang, $request);
                $data['action'] = 'newtopic';
                break;
            case 'quotepost':
                $data['compose'] = $this->buildQuotePost($lang, $curUser, $request);
                $data['action'] = 'quotepost';
                break;
            case 'reply':
                $data['compose'] = $this->buildReply($lang, $request);
                $data['action'] = 'reply';
                break;
            case 'editpost':
                $data['compose'] = $this->buildEditPost($lang, $curUser, $request);
                $data['action'] = 'editpost';
                break;
            case 'viewtopic':
                $data['viewtopic'] = $this->buildViewTopic($lang, $curUser, $userId, $request, $postsperpage);
                $data['action'] = 'viewtopic';
                break;
            case 'viewforum':
                $data['viewforum'] = $this->buildViewForum($lang, $curUser, $request, $topicsperpage, $postsperpage);
                $data['action'] = 'viewforum';
                break;
            case 'viewunread':
                $data['viewunread'] = $this->buildViewUnread($lang, $curUser);
                $data['action'] = 'viewunread';
                break;
            case 'search':
                $data['search'] = $this->buildSearch($lang, $topicsperpage);
                $data['action'] = 'search';
                break;
            default:
                if ($action !== '') {
                    LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_unknown_action'] ?? '');
                }
                $data['forums'] = $this->buildForumsIndex($lang, $curUser, $userId);
                $data['action'] = 'forums';
                break;
        }

        return $data;
    }

    /**
     * Build the compose-frame HTML for the requested type.
     *
     * @param  array<string, mixed>  $lang
     * @return array{title: string, body: string}
     */
    private function buildComposeFrame(int $id, string $type, array $lang): array
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
                $forumname = ForumRepository::getForumName((int) $id) ?? '';
                $title = ($lang['text_new_topic_in'] ?? '').' <a href="'.htmlspecialchars('?action=viewforum&forumid='.$id).'">'.htmlspecialchars($forumname).'</a> '.($lang['text_forum'] ?? '');
                $hassubject = true;
                break;

            case 'reply':
                $topicname = ForumRepository::getTopicSubject((int) $id) ?? '';
                $title = ($lang['text_reply_to_topic'] ?? '').' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$id).'">'.htmlspecialchars($topicname).'</a> ';
                break;

            case 'quote':
                $post = ForumRepository::getPostForQuote((int) $id);
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
                $post = ForumRepository::getPostForEdit((int) $id);
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
    private function buildNewTopic(array $lang, Request $request): array
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
    private function buildQuotePost(array $lang, array $curUser, Request $request): array
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
    private function buildReply(array $lang, Request $request): array
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
    private function buildEditPost(array $lang, array $curUser, Request $request): array
    {
        $postid = (int) (request()->query('postid') ?? 0);
        $this->checkWhetherExist($postid, 'post', $lang);

        $post = ForumRepository::getPostWithTopic((int) $postid);
        if (! $post) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_post_id'] ?? '');

            return ['title' => '', 'body' => ''];
        }

        $locked = $post['locked'] == 'yes';
        $ismod = Forum::isModerator($postid, 'post');
        if (($curUser['id'] != $post['userid'] || $locked) && ! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod) {
            LegacyResponse::permissionDenied();
        }

        return $this->buildComposeFrame($postid, 'edit', $lang);
    }

    /**
     * Build the view-topic section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewTopic(array $lang, array $curUser, int $userId, Request $request, int $postsperpage): array
    {
        $Cache = app(LegacyRedisCache::class);
        $highlight = htmlspecialchars(trim((string) (request()->query('highlight') ?? '')));
        $topicid = (int) (request()->query('topicid') ?? 0);
        LegacyResponse::assertId($topicid, true);
        $page = is_string($val = request()->query('page')) ? $val : 0;
        $authorid = (int) (request()->query('authorid') ?? 0);
        if ($authorid) {
            $addparam = 'action=viewtopic&topicid='.$topicid.'&authorid='.$authorid;
        } else {
            $addparam = 'action=viewtopic&topicid='.$topicid;
        }

        $topic = ForumRepository::getTopic((int) $topicid);
        if (! $topic) {
            LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_topic_not_found'] ?? '');

            return [];
        }
        $arr = $topic->toArray();

        $forumid = (int) $arr['forumid'];
        $locked = $arr['locked'] == 'yes';
        $orgsubject = (string) $arr['subject'];
        $subject = htmlspecialchars((string) $arr['subject']);
        if ($highlight) {
            $subject = Format::highlight($highlight, $orgsubject);
        }
        $sticky = $arr['sticky'] == 'yes';
        $hlcolor = (int) $arr['hlcolor'];
        $views = (int) $arr['views'];
        $basePosterid = (int) $arr['userid'];

        $row = $this->getForumRow($forumid);
        $forumname = (string) ($row['name'] ?? '');
        $isForummod = Forum::isModerator($forumid, 'forum');

        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_unpermitted_viewing_topic'] ?? '');
        }
        if (((UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && ! $locked) || Permission::can(PermissionEnum::POST_MANAGE) || $isForummod) && ($curUser['forumpost'] ?? '') == 'yes') {
            $maypost = true;
        } else {
            $maypost = false;
        }

        ForumRepository::incrementTopicViews((int) $topicid);

        $postcount = ForumRepository::countTopicPosts((int) $topicid, $authorid ?: null);
        if (! $authorid) {
            $Cache?->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);
        }

        $pagerarr = [];
        $perpage = $postsperpage;
        $pages = (int) ceil($postcount / max(1, $perpage));

        if ((isset($page[0])) && $page[0] == 'p') {
            $findpost = substr($page, 1);
            $postIds = ForumRepository::getTopicPostIds((int) $topicid, $authorid ?: null);
            $i = array_search($findpost, $postIds);
            if ($i === false) {
                $i = 0;
            }
            $page = floor((int) $i / $perpage);
        }
        if ($page === 'last') {
            $page = $pages - 1;
        } elseif ($page < 0) {
            $page = 0;
        } elseif ($page > $pages - 1) {
            $page = $pages - 1;
        } elseif (($curUser['clicktopic'] ?? '') == 'firstpage') {
            $page = 0;
        } else {
            $page = $pages - 1;
        }

        $offset = $page * $perpage;
        $dotted = 0;
        $dotspace = 3;
        $dotend = $pages - $dotspace;
        $curdotend = $page - $dotspace;
        $curdotstart = $page + $dotspace;
        for ($i = 0; $i < $pages; $i++) {
            if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
                if (! $dotted) {
                    $pagerarr[] = '...';
                }
                $dotted = 1;

                continue;
            }
            $dotted = 0;
            if ($i != $page) {
                $pagerarr[] = '<a href="'.htmlspecialchars('?'.$addparam.'&page='.$i).'"><b>'.($i + 1)."</b></a>\n";
            } else {
                $pagerarr[] = '<font class="gray"><b>'.($i + 1)."</b></font>\n";
            }
        }
        if ($page == 0) {
            $pager = '<font class="gray"><b>&lt;&lt;'.($lang['text_prev'] ?? '').'</b></font>';
        } else {
            $pager = '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page - 1)).
            '"><b>&lt;&lt;'.($lang['text_prev'] ?? '').'</b></a>';
        }
        $pager .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        if ($page == $pages - 1) {
            $pager .= '<font class="gray"><b>'.($lang['text_next'] ?? '')." &gt;&gt;</b></font>\n";
        } else {
            $pager .= '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page + 1)).
            '"><b>'.($lang['text_next'] ?? '')." &gt;&gt;</b></a>\n";
        }

        $pagerstr = implode(' | ', $pagerarr);
        $pagertop = '<p align="center">'.$pager.'<br />'.$pagerstr."</p>\n";
        $pagerbottom = '<p align="center">'.$pagerstr.'<br />'.$pager."</p>\n";

        $postRows = ForumRepository::getTopicPosts((int) $topicid, $authorid ?: null, (int) $offset, (int) $perpage);
        $pc = $postRows->count();
        $allPosts = [];
        $uidArr = [];
        foreach ($postRows as $postObj) {
            $arr = $postObj->toArray();
            $allPosts[] = $arr;
            $uidArr[$arr['userid']] = 1;
        }
        $uidArr = array_keys($uidArr);
        unset($arr);

        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');

        ob_start();
        echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.($lang['text_forums'] ?? '').'</a>--><a class="faqlink" href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'">'.$forumname.'</a><b>--></b><span id="top">'.$subject.($locked ? '&nbsp;&nbsp;<b>[<font class="striking">'.($lang['text_locked'] ?? '').'</font>]</b>' : '')."</span></h1>\n";
        echo $pagertop;

        echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
        echo '<td class="embedded" width="99%">&nbsp;&nbsp;'.($lang['there_is'] ?? '').'<b>'.$views.'</b>'.($lang['hits_on_this_topic'] ?? '');
        echo "</td>\n";
        echo '<td class="embedded nowrap" width="1%" align="right">';
        if ($maypost) {
            echo '<a href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="'.($lang['title_reply_directly'] ?? '').'" /></a>&nbsp;&nbsp;';
        }
        echo '</td>';
        echo "</tr></table>\n";
        Html::beginFrame();

        $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
        $userInfoArr = ForumRepository::getUsersByIds($uidArr, $neededColumns);
        $pn = 0;
        $lpr = $this->getLastReadPostId($topicid, $curUser);

        $__server_REQUEST_URI = Input::serverValue('REQUEST_URI');

        foreach ($allPosts as $arr) {
            $pn++;

            $postid = (int) $arr['id'];
            $posterid = (int) $arr['userid'];

            $added = Time::format($arr['added'], true, false);

            $userInfo = $userInfoArr->get($posterid) ?: User::defaultUser();
            $arr2 = $userInfo->toArray();

            $uploaded = Format::size($arr2['uploaded']);
            $downloaded = Format::size($arr2['downloaded']);
            $ratio = Ratio::forUserId((int) $arr2['id']);

            if (! $forumposts = $Cache?->get_value('user_'.$posterid.'_post_count')) {
                $forumposts = ForumRepository::countUserPosts((int) $posterid);
                $Cache?->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
            }

            $signature = (($curUser['signatures'] ?? '') == 'yes' ? ($arr2['signature'] ?? '') : '');
            $avatar = (($curUser['avatars'] ?? '') == 'yes' ? htmlspecialchars((string) ($arr2['avatar'] ?? '')) : '');

            $uclass = UserClass::imagePath((int) ($arr2['class'] ?? 0));
            $by = UserDisplay::username($posterid, false, true, true, false, false, true);

            if (! $avatar) {
                $avatar = 'pic/default_avatar.png';
            }

            if ($pn == $pc) {
                echo "<span id=\"last\"></span>\n";
                if ($postid > $lpr) {
                    ForumRepository::markPostRead((int) $userId, (int) $topicid, (int) $postid, (int) ($curUser['last_catchup'] ?? 0));
                    $Cache?->delete_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list');
                }
            }

            echo '<div style="margin-top: 8pt; margin-bottom: 8pt;"><table id="pid'.$postid.'" border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td class="embedded" width="99%"><a href="'.htmlspecialchars('forums.php?action=viewtopic&topicid='.$topicid.'&page=p'.$postid.'#pid'.$postid).'">#'.$postid.'</a>&nbsp;&nbsp;<font color="gray">'.($lang['text_by'] ?? '').'</font>'.$by.'&nbsp;&nbsp;<font color="gray">'.($lang['text_at'] ?? '').'</font>'.$added;
            if (Validators::isId($arr['editedby'])) {
                echo '';
            }
            echo '&nbsp;&nbsp;<font color="gray">|</font>&nbsp;&nbsp;';
            if ($authorid) {
                echo '<a href="?action=viewtopic&topicid='.$topicid.'">'.($lang['text_view_all_posts'] ?? '').'</a>';
            } else {
                echo '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.'&authorid='.$posterid).'">'.($lang['text_view_this_author_only'] ?? '').'</a>';
            }
            echo '</td><td class="embedded nowrap" width="1%"><font class="big">'.($lang['text_number'] ?? '').'<b>'.($pn + $offset).'</b>'.($lang['text_lou'] ?? '').'&nbsp;&nbsp;</font><a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="'.($lang['text_back_to_top'] ?? '').'" /></a>&nbsp;&nbsp;</td></tr>';

            echo "</table></div>\n";

            echo "<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";

            $body = '<div id="pid'.$postid.'body" style="word-break: break-all;">';
            if ($pn + $offset > 1 && ! Forum::canViewPost($userId, $arr)) {
                $bodyContent = Format::formatComment((string) ($lang['text_post_protected'] ?? ''));
                $canViewProtected = false;
            } else {
                $bodyContent = Format::formatComment((string) $arr['body']);
                $canViewProtected = true;
            }
            if ($highlight) {
                $bodyContent = Format::highlight($highlight, $bodyContent);
            }

            if (Validators::isId($arr['editedby'])) {
                $lastedittime = Time::format($arr['editdate'], true, false);
                $bodyContent .= '<br /><p><font class="small">'.($lang['text_last_edited_by'] ?? '').UserDisplay::username((int) $arr['editedby']).($lang['text_last_edit_at'] ?? '').$lastedittime."</font></p>\n";
            }
            $body .= $bodyContent.'</div>';
            if ($signature) {
                $body .= "<p style='vertical-align:bottom'><br />____________________<br />".Format::formatComment($signature, false, false, false, true, 500, true, false, 1, 200).'</p>';
            }

            $stats = '<br />'.'&nbsp;&nbsp;'.($lang['text_posts'] ?? '')."$forumposts<br />".'&nbsp;&nbsp;'.($lang['text_ul'] ?? '')."$uploaded <br />".'&nbsp;&nbsp;'.($lang['text_dl'] ?? '')."$downloaded<br />".'&nbsp;&nbsp;'.($lang['text_ratio'] ?? '')."$ratio";
            echo "<tr><td class=\"rowfollow\" width=\"150\" valign=\"top\" align=\"left\" style='padding: 0px'>".
            UserDisplay::avatarImageWithContext($avatar).'<br /><br /><br />&nbsp;&nbsp;<img alt="'.UserClass::name((int) ($arr2['class'] ?? 0), false, false, true).'" title="'.UserClass::name((int) ($arr2['class'] ?? 0), false, false, true).'" src="'.$uclass.'" />'.$stats.'</td><td class="rowfollow" valign="top"><br />'.$body."</td></tr>\n";
            $secs = 900;
            $dt = date('Y-m-d H:i:s', (int) (defined('TIMENOW') ? constant('TIMENOW') : time()) - $secs);
            $online = ($arr2['last_access'] ?? '') > $dt;
            echo '<tr><td class="rowfollow" align="center" valign="middle">'.($online ? '<img class="f_online" src="pic/trans.gif" alt="Online" title="'.($lang['title_online'] ?? '').'" />' : '<img class="f_offline" src="pic/trans.gif" alt="Offline" title="'.($lang['title_offline'] ?? '').'" />').'<a href="sendmessage.php?receiver='.htmlspecialchars(trim((string) ($arr2['id'] ?? ''))).'"><img class="f_pm" src="pic/trans.gif" alt="PM" title="'.($lang['title_send_message_to'] ?? '').htmlspecialchars((string) ($arr2['username'] ?? ''))."\" /></a><a href=\"report.php?forumpost=$postid\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".($lang['title_report_this_post'] ?? '').'" /></a></td>';
            echo '<td class="toolbox" align="right">';

            if ($maypost && $canViewProtected) {
                echo '<a href="'.htmlspecialchars('?action=quotepost&postid='.$postid).'"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="'.($lang['title_reply_with_quote'] ?? '').'" /></a>';
            }

            if (Permission::can(PermissionEnum::POST_MANAGE) || $isForummod) {
                echo '<a href="'.htmlspecialchars('?action=deletepost&postid='.$postid).'"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="'.($lang['title_delete_post'] ?? '').'" /></a>';
            }

            if (($curUser['id'] == $posterid && ! $locked) || Permission::can(PermissionEnum::POST_MANAGE) || $isForummod) {
                echo '<a href="'.htmlspecialchars('?action=editpost&postid='.$postid).'"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="'.($lang['title_edit_post'] ?? '').'" /></a>';
            }
            echo '</td></tr></table>';
        }

        // ------ Mod options
        if (Permission::can(PermissionEnum::POST_MANAGE) || $isForummod) {
            echo "</td></tr><tr><td class=\"toolbox\" align=\"center\">\n";
            echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n";
            echo "<tr><td class=\"embedded\"><form method=\"post\" action=\"?action=setsticky\">\n";
            echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
            echo '<input type="hidden" name="returnto" value="'.htmlspecialchars((string) $__server_REQUEST_URI)."\" />\n";
            echo '<input type="hidden" name="sticky" value="'.($sticky ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($sticky ? ($lang['submit_unsticky'] ?? '') : ($lang['submit_sticky'] ?? ''))."\" /></form></td>\n";
            echo "<td class=\"embedded\"><form method=\"post\" action=\"?action=setlocked\">\n";
            echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
            echo '<input type="hidden" name="returnto" value="'.htmlspecialchars((string) $__server_REQUEST_URI)."\" />\n";
            echo '<input type="hidden" name="locked" value="'.($locked ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($locked ? ($lang['submit_unlock'] ?? '') : ($lang['submit_lock'] ?? ''))."\" /></form></td>\n";
            echo "<td class=\"embedded\"><form method=\"get\" action=\"?\">\n";
            echo "<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n";
            echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
            echo '<input type="hidden" name="forumid" value="'.$forumid."\" />\n";
            echo '<input type="submit" class="medium" value="'.($lang['submit_delete_topic'] ?? '')."\" /></form></td>\n";
            echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=movetopic&topicid='.$topicid)."\">\n".'&nbsp;'.($lang['text_move_thread_to'] ?? '').'&nbsp;<select class="med" name="forumid">';
            $forums = $this->getForumRow(0);
            foreach ($forums ?? [] as $arr) {
                if ($arr['id'] != $forumid && UserDisplay::currentClass() >= (int) $arr['minclasswrite']) {
                    echo '<option value="'.$arr['id'].'">'.htmlspecialchars((string) $arr['name'])."</option>\n";
                }
            }
            echo '</select> <input type="submit" class="medium" value="'.($lang['submit_move'] ?? '').'" /></form></td>';
            echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=hltopic&topicid='.$topicid)."\">\n".'&nbsp;'.($lang['text_highlight_topic'] ?? '').'&nbsp;<select class="med" name="color">';
            echo $this->highlightColorOptions((string) ($lang['select_color'] ?? ''));
            echo '</select>';
            echo '<input type="hidden" name="returnto" value="'.htmlspecialchars((string) $__server_REQUEST_URI)."\" />\n";
            echo '<input type="submit" class="medium" value="'.($lang['submit_change'] ?? '').'" /></form></td>';
            echo "</tr>\n";
            echo "</table>\n";
        }

        Html::endFrame();

        echo $pagerbottom;
        if ($maypost) {
            echo "<br /><table style='border:1px solid #000000;'><tr>".
'<td class="text" align="center"><b>'.($lang['text_quick_reply'] ?? '').'</b><br /><br />'.
'<form id="compose" name="compose" method="post" action="?action=post" onsubmit="return postvalid(this);">'.
'<input type="hidden" name="id" value="'.$topicid.'" /><input type="hidden" name="type" value="reply" /><br />';
            Html::quickReplyVoid('compose', 'body', (string) ($lang['submit_add_reply'] ?? ''));
            echo '</form></td></tr></table>';
            echo '<p align="center"><a class="index" href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'">'.($lang['text_add_reply'] ?? '')."</a></p>\n";
        } elseif ($locked) {
            echo $lang['text_topic_locked_new_denied'] ?? '';
        } else {
            echo $lang['text_unpermitted_posting_here'] ?? '';
        }

        echo Html::keyShortcutScript((int) $page, max(0, $pages - 1));

        return [
            'html' => (string) ob_get_clean(),
            'topicid' => $topicid,
            'forumid' => $forumid,
            'subject' => $subject,
        ];
    }

    /**
     * Build the view-forum section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewForum(array $lang, array $curUser, Request $request, int $topicsperpage, int $postsperpage): array
    {
        $Cache = app(LegacyRedisCache::class);
        $forumid = (int) (request()->query('forumid') ?? 0);
        LegacyResponse::assertId($forumid, true);
        $userid = (int) ($curUser['id'] ?? 0);

        $row = $this->getForumRow($forumid);
        if (! $row) {
            Log::writeWithContext('User '.($curUser['username'] ?? '').','.($curUser['ip'] ?? '')." is trying to visit forum that doesn't exist", 'mod');
            LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_forum_not_found'] ?? '');
        }
        if (UserDisplay::currentClass() < (int) ($row['minclassread'] ?? 0)) {
            LegacyResponse::permissionDenied();
        }

        $forumname = (string) ($row['name'] ?? '');
        $forummoderators = Forum::moderatorsWithContext($forumid, false);
        $search = trim(is_scalar(request()->query('search') ?? '') ? (string) (request()->query('search') ?? '') : '');
        if ($search) {
            $addparam = '&search='.rawurlencode($search);
        } else {
            $addparam = '';
        }

        $sort = (string) (request()->query('sort') ?? 'lastpostdesc');
        switch ($sort) {
            case 'firstpostasc':
                $sortColumn = 'firstpost';
                $sortDirection = 'asc';
                break;
            case 'firstpostdesc':
                $sortColumn = 'firstpost';
                $sortDirection = 'desc';
                break;
            case 'lastpostasc':
                $sortColumn = 'lastpost';
                $sortDirection = 'asc';
                break;
            case 'lastpostdesc':
                $sortColumn = 'lastpost';
                $sortDirection = 'desc';
                break;
            default:
                $sortColumn = 'lastpost';
                $sortDirection = 'desc';
        }

        $topicResult = ForumRepository::getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, 0, 0);
        $num = (int) $topicResult['count'];

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($topicsperpage, $num, '?'.'action=viewforum&forumid='.$forumid.$addparam.'&');
        $topicResult = ForumRepository::getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, (int) $offset, (int) $perpage);
        $topicRows = $topicResult['rows'];
        $numtopics = $topicRows->count();

        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');
        $enabletooltipTweak = (string) app(Globals::class)->get('enabletooltip_tweak', '');

        ob_start();
        echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.($lang['text_forums'] ?? '').'</a>--><a class="faqlink" href="'.htmlspecialchars('forums.php?action=viewforum&forumid='.$forumid).'">'.$forumname."</a></h1>\n";
        echo '<br />';
        $maypost = UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && UserDisplay::currentClass() >= (int) ($row['minclasscreate'] ?? 0) && ($curUser['forumpost'] ?? '') == 'yes';

        if (! $maypost) {
            echo '<p><i>'.($lang['text_unpermitted_starting_new_topics'] ?? '')."</i></p>\n";
        }

        echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
        echo '<td class="embedded" width="90%">';
        echo $forummoderators ? '&nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="'.($lang['col_moderator'] ?? '').'">&nbsp;'.$forummoderators : '';
        echo '</td><td class="embedded nowrap" width="1%">';
        if ($maypost) {
            echo '<a href="'.htmlspecialchars('?action=newtopic&forumid='.$forumid).'"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="'.($lang['title_new_topic'] ?? '').'" /></a>&nbsp;&nbsp;';
        }
        echo '</td>';
        echo "</tr></table>\n";
        if ($numtopics > 0) {
            echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';

            $sortToggleFirst = (((request()->query('sort') !== null)) && request()->query('sort') == 'firstpostdesc') ? 'firstpostasc' : 'firstpostdesc';
            $sortToggleFirstTitle = (((request()->query('sort') !== null)) && request()->query('sort') == 'firstpostdesc') ? ($lang['title_order_topic_asc'] ?? '') : ($lang['title_order_topic_desc'] ?? '');
            $sortToggleLast = (((request()->query('sort') !== null)) && request()->query('sort') == 'lastpostasc') ? 'lastpostdesc' : 'lastpostasc';
            $sortToggleLastTitle = (((request()->query('sort') !== null)) && request()->query('sort') == 'lastpostasc') ? ($lang['title_order_post_desc'] ?? '') : ($lang['title_order_post_asc'] ?? '');

            echo '<tr><td class="colhead" align="center" width="99%">'.($lang['col_topic'] ?? '').'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.$sortToggleFirst).'" title="'.$sortToggleFirstTitle.'">'.($lang['col_author'] ?? '').'</a></td><td class="colhead" align="center">'.($lang['col_replies'] ?? '').'/'.($lang['col_views'] ?? '').'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.$sortToggleLast).'" title="'.$sortToggleLastTitle.'">'.($lang['col_last_post'] ?? '')."</a></td>\n";

            echo "</tr>\n";
            $counter = 0;
            $lastpost_tooltip = [];

            foreach ($topicRows as $topic) {
                $topicarr = $topic->toArray();
                $topicid = (int) $topicarr['id'];
                $topic_userid = (int) $topicarr['userid'];
                $topic_views = (int) $topicarr['views'];
                $views = number_format($topic_views);
                $locked = $topicarr['locked'] == 'yes';
                $sticky = $topicarr['sticky'] == 'yes';
                $hlcolor = (int) $topicarr['hlcolor'];

                if (! $posts = $Cache?->get_value('topic_'.$topicid.'_post_count')) {
                    $posts = ForumRepository::countTopicPosts((int) $topicid);
                    $Cache?->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
                }

                $replies = max(0, $posts - 1);
                $tpages = (int) floor($posts / max(1, $postsperpage));
                if ($tpages * $postsperpage != $posts) {
                    $tpages++;
                }

                if ($tpages > 1) {
                    $topicpages = ' [<img class="multipage" src="pic/trans.gif" alt="multi-page" /> ';
                    $dotted = 0;
                    $dotspace = 4;
                    $dotend = $tpages - $dotspace;
                    for ($i = 1; $i <= $tpages; $i++) {
                        if ($i > $dotspace && $i <= $dotend) {
                            if (! $dotted) {
                                $topicpages .= ' ... ';
                            }
                            $dotted = 1;

                            continue;
                        }
                        $topicpages .= ' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.'&page='.($i - 1))."\">$i</a>";
                    }
                    $topicpages .= ' ]';
                } else {
                    $topicpages = '';
                }

                $arr = Forum::postRowWithContext((int) $topicarr['lastpost']);
                $lppostid = (int) ($arr['id'] ?? 0);
                $lpuserid = (int) ($arr['userid'] ?? 0);
                $lpusername = UserDisplay::username($lpuserid);
                $lpadded = Time::format($arr['added'] ?? '', true, false);
                $onmouseover = '';
                $lastpost_tooltip = [];
                if ($enabletooltipTweak == 'yes' && ($curUser['showlastpost'] ?? '') != 'no') {
                    if (($curUser['timetype'] ?? '') != 'timealive') {
                        $lastposttime = ($lang['text_at_time'] ?? '').($arr['added'] ?? '');
                    } else {
                        $lastposttime = ($lang['text_blank'] ?? '').Time::format($arr['added'] ?? '', true, false, true);
                    }
                    $lptext = Format::formatComment(mb_substr((string) ($arr['body'] ?? ''), 0, 100, 'UTF-8').(mb_strlen((string) ($arr['body'] ?? ''), 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                    $lastpost_tooltip[$counter]['id'] = 'lastpost_'.$counter;
                    $lastpost_tooltip[$counter]['content'] = ($lang['text_last_posted_by'] ?? '').$lpusername.$lastposttime.'<br />'.$lptext;
                    $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastpost_tooltip[$counter]['id']."'), 'trail', false,'lifetime', 5000,'styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                }

                $arr = Forum::postRowWithContext((int) $topicarr['firstpost']);
                $fpuserid = (int) ($arr['userid'] ?? 0);
                $fpauthor = UserDisplay::username((int) ($arr['userid'] ?? 0));

                $subject = ($sticky ? '<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.($lang['title_sticky'] ?? '').'" />&nbsp;&nbsp;' : '').'<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid).'" '.$onmouseover.'>'.$this->highlightTopic(Format::highlight($search, htmlspecialchars((string) $topicarr['subject'])), $hlcolor).'</a>'.$topicpages;
                $lastpostread = $this->getLastReadPostId($topicid, $curUser);

                if ($lastpostread >= $lppostid) {
                    $img = $this->getTopicImage($locked ? 'locked' : 'read', $lang);
                } else {
                    $img = $this->getTopicImage($locked ? 'lockednew' : 'unread', $lang);
                    if ($lastpostread != (int) ($curUser['last_catchup'] ?? 0)) {
                        $subject .= '&nbsp;&nbsp;<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid.'&page=p'.$lastpostread.'#pid'.$lastpostread).'" title="'.($lang['title_jump_to_unread'] ?? '').'"><font class="small new"><b>'.($lang['text_new'] ?? '').'</b></font></a>';
                    }
                }

                $topictime = substr((string) ($arr['added'] ?? ''), 0, 10);
                if (strtotime((string) ($arr['added'] ?? '')) + 86400 > (int) (defined('TIMENOW') ? constant('TIMENOW') : time())) {
                    $topictime = '<font class="new small">'.$topictime.'</font>';
                } else {
                    $topictime = '<font color="gray" class="small">'.$topictime.'</font>';
                }

                echo '<tr><td class="rowfollow" align="left"><table border="0" cellspacing="0" cellpadding="0"><tr>'.
                "<td class=\"embedded\" style='padding-right: 10px'>".$img.
                "</td><td class=\"embedded\" align=\"left\">\n".
                $subject.'</td></tr></table></td><td class="rowfollow" align="center">'.UserDisplay::username($fpuserid).'<br />'.$topictime.'</td><td class="rowfollow" align="center">'.$replies.' / <font color="gray">'.$views."</font></td>\n".
                '<td class="rowfollow nowrap" align="center">'.$lpadded.'<br />'.$lpusername."</td>\n";

                echo "</tr>\n";
                $counter++;
            }

            echo "<tr><td align=\"left\">\n";
            echo '<form method="get" action="forums.php"><b>'.($lang['text_fast_search'] ?? '').'</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="'.$forumid.'" /><input type="text" style="width: 180px" name="search" />&nbsp;<input type="submit" value="'.($lang['text_go'] ?? '').'" /></form>';
            echo '</td>';
            ?>
<td align="left" colspan="3">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;"><b><?php echo $lang['text_order'] ?? '' ?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=firstpostdesc"><?php echo $lang['text_topic_desc'] ?? '' ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=firstpostasc"><?php echo $lang['text_topic_asc'] ?? '' ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=lastpostdesc"><?php echo $lang['text_post_desc'] ?? '' ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=lastpostasc"><?php echo $lang['text_post_asc'] ?? '' ?></a></li>
</ul>
</span>
</span>
</td>
<?php
            echo '</tr></table>';
            echo $pagerbottom;
            if ($enabletooltipTweak == 'yes' && ($curUser['showlastpost'] ?? '') != 'no') {
                echo Html::tooltipContainer($lastpost_tooltip, 400);
            }
        } else {
            echo '<p>'.($lang['text_no_topics_found'] ?? '').'</p>';
        }

        return [
            'html' => (string) ob_get_clean(),
            'forumid' => $forumid,
            'forumname' => $forumname,
        ];
    }

    /**
     * Build the view-unread-posts section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewUnread(array $lang, array $curUser): array
    {
        $userid = (int) ($curUser['id'] ?? 0);
        $beforepostid = (int) (request()->query('beforepostid') ?? 0);
        $maxresults = 25;
        $lastCatchup = (int) ($curUser['last_catchup'] ?? 0);
        $unreadTopics = ForumRepository::getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');

        ob_start();
        echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.($lang['text_forums'] ?? '').'</a>-->'.($lang['text_topics_with_unread_posts'] ?? '').'</h1>';

        $n = 0;
        $uc = UserDisplay::currentClass();
        $topiclastpost = 0;

        foreach ($unreadTopics as $topic) {
            $arr = $topic->toArray();
            $topiclastpost = (int) $arr['lastpost'];
            $topicid = (int) $arr['id'];

            $lastpostread = $this->getLastReadPostId($topicid, $curUser);

            if ($lastpostread >= $topiclastpost) {
                continue;
            }

            $forumid = (int) $arr['forumid'];
            $a = $this->getForumRow($forumid);
            if ($uc < (int) ($a['minclassread'] ?? 0)) {
                continue;
            }
            $n++;
            if ($n > $maxresults) {
                break;
            }

            $forumname = (string) ($a['name'] ?? '');
            if ($n == 1) {
                echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
                echo '<tr><td class="colhead" align="left">'.($lang['col_topic'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_forum'] ?? '')."</td></tr>\n";
            }
            echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".
            $this->getTopicImage('unread', $lang).'</td><td class="embedded">'.
            '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.($lastpostread > 0 && $lastpostread != (int) ($curUser['last_catchup'] ?? 0) ? '&page=p'.$lastpostread.'#pid'.$lastpostread : '')).'">'.$this->highlightTopic(htmlspecialchars((string) $arr['subject']), (int) $arr['hlcolor']).
            '</a></td></tr></table></td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><b>'.$forumname."</b></a></td></tr>\n";
        }
        if ($n > 0) {
            echo "</table>\n";
            echo '<table border="0" class="main" cellspacing="0" cellpadding="5" width="1%"><tr><td class="embedded"><form method="get" action="?"><input type="hidden" name="catchup" value="1" /><input type="submit" value="'.($lang['text_catch_up'] ?? '').'" class="btn" /></form></td>';
            if ($n > $maxresults) {
                echo '<td class="embedded"><form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="'.$topiclastpost.'" /><input type="submit" value="'.($lang['submit_show_more'] ?? '').'" class="btn" /></form></td>';
            }
            echo '</tr></table>';
        } else {
            echo '<p>'.($lang['text_nothing_found'] ?? '').'</p>';
        }

        return ['html' => (string) ob_get_clean()];
    }

    /**
     * Build the forum search section.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildSearch(array $lang, int $topicsperpage): array
    {
        $error = true;
        $found = '';
        $keywords = htmlspecialchars(trim((string) (request()->query('keywords') ?? '')));
        if ($keywords != '') {
            $searchResult = ForumRepository::searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), 0, 0);
            $hits = (int) $searchResult['hits'];
            if ($hits) {
                $error = false;
                $found = '[<b><font class="striking"> '.($lang['text_found'] ?? '').$hits.($lang['text_num_posts'] ?? '').' </font></b>]';
            }
        }

        ob_start();
        ?>
<style type="text/css">
.search{
	background-image:url(pic/search.gif);
	background-repeat:no-repeat;
	width:579px;
	height:95px;
	margin:5px 0 5px 0;
	text-align:left;
}
.search_title{
	color:#0062AE;
	background-color:#DAF3FB;
	font-size:12px;
	font-weight:bold;
	text-align:left;
	padding:7px 0 0 15px;
}

.search_table {
	border-collapse: collapse;
	border: none;
	background-color: #ffffff;
}

</style>
<div class="search">
	<div class="search_title"><?php echo $lang['text_search_on_forum'] ?? '' ?> <?php echo $error && $keywords != '' ? '[<b><font color=striking> '.($lang['text_nothing_found'] ?? '').'</font></b> ]' : $found ?></div>
	<div style="margin-left: 53px; margin-top: 13px;">
		<form method="get" action="forums.php" id="search_form" style="margin: 0pt; padding: 0pt; font-family: Tahoma,Arial,Helvetica,sans-serif; font-size: 11px;">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top"><?php echo $lang['text_by_keyword'] ?? '' ?></td>
		</tr>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords ?>" style="width: 400px;" /></td>
			<td style="padding-bottom: 3px; border: 0;" valign="top"><input name="image" type="image" style="vertical-align: middle; padding-bottom: 0px; margin-left: 0px;" src="<?php echo Forum::picFolderWithContext() ?>/search_button.gif" alt="Search" /></td>
		</tr>
		</tbody>
		</table>
		</form>
	</div>
</div>
<?php
        if (! $error) {
            $perpage = $topicsperpage;
            [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $hits, 'forums.php?action=search&keywords='.rawurlencode($keywords).'&');
            $searchResult = ForumRepository::searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), (int) $offset, (int) $perpage);
            $posts = $searchResult['rows'];

            echo $pagertop;
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n";
            echo '<tr><td class="colhead" align="center">'.($lang['col_post'] ?? '').'</td><td class="colhead" align="center" width="70%">'.($lang['col_topic'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_forum'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_posted_by'] ?? '')."</td></tr>\n";

            foreach ($posts as $post) {
                $post = (array) $post;
                echo '<tr><td class="rowfollow" align="center" width="1%">'.$post['id'].'</td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewtopic&topicid='.$post['topicid'].'&highlight='.rawurlencode($keywords).'&page=p'.$post['id'].'#pid'.$post['id']).'">'.$this->highlightTopic(Format::highlight($keywords, htmlspecialchars((string) $post['subject'])), (int) $post['hlcolor']).'</a></td><td class="rowfollow nowrap" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$post['forumid']).'"><b>'.htmlspecialchars((string) $post['forumname']).'</b></a></td><td class="rowfollow nowrap" align="left">'.Time::format($post['added'], true, false).'&nbsp;|&nbsp;'.UserDisplay::username((int) $post['userid'])."</td></tr>\n";
            }

            echo "</table>\n";
            echo $pagerbottom;
        }

        return ['html' => (string) ob_get_clean()];
    }

    /**
     * Build the default forums index (overforums + forums list + stats).
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildForumsIndex(array $lang, array $curUser, int $userId): array
    {
        $Cache = app(LegacyRedisCache::class);
        $todayDate = date('Y-m-d');

        if ($curUser) {
            ForumRepository::updateUserForumAccess((int) ($curUser['id'] ?? 0), date('Y-m-d H:i:s'));
        }

        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');
        $showforumstatsMain = (string) app(Globals::class)->get('showforumstats_main', '');

        ob_start();
        echo '<h1 align="center">'.$SITENAME.'&nbsp;'.($lang['text_forums'] ?? '').'</h1>';
        echo '<p align="center"><a href="?action=search"><b>'.($lang['text_search'] ?? '').'</b></a> | <a href="?action=viewunread"><b>'.($lang['text_view_unread'] ?? '').'</b></a> | <a href="?catchup=1"><b>'.($lang['text_catch_up'] ?? '').'</b></a> '.(Permission::can(PermissionEnum::FORUM_MANAGE) ? '| <a href="forummanage.php"><b>'.($lang['text_forum_manager'] ?? '').'</b></a>' : '').'</p>';
        echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n";

        if (! $overforums = $Cache?->get_value('overforums_list')) {
            $overforums = ForumRepository::getOverforumsList();
            $Cache?->cache_value('overforums_list', $overforums, 86400);
        }
        foreach ($overforums as $a) {
            if (UserDisplay::currentClass() < (int) ($a['minclassview'] ?? 0)) {
                continue;
            }
            $forid = (int) $a['id'];
            $overforumname = (string) ($a['name'] ?? '');

            echo '<tr><td align="left" class="colhead" width="99%">'.htmlspecialchars($overforumname).'</td><td align="center" class="colhead">'.($lang['col_topics'] ?? '').'</td>'.
            '<td align="center" class="colhead">'.($lang['col_posts'] ?? '').'</td>'.
            '<td align="left" class="colhead">'.($lang['col_last_post'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_moderator'] ?? '')."</td></tr>\n";

            $forums = $this->getForumRow(0);
            foreach ($forums ?? [] as $forums_arr) {
                if ((int) $forums_arr['forid'] != $forid) {
                    continue;
                }
                if (UserDisplay::currentClass() < (int) ($forums_arr['minclassread'] ?? 0)) {
                    continue;
                }

                $forumid = (int) $forums_arr['id'];
                $forumname = htmlspecialchars((string) ($forums_arr['name'] ?? ''));
                $forumdescription = htmlspecialchars((string) ($forums_arr['description'] ?? ''));

                $forummoderators = Forum::moderatorsWithContext($forumid, false);
                if (! $forummoderators) {
                    $forummoderators = '<a href="contactstaff.php"><i>'.($lang['text_apply_now'] ?? '').'</i></a>';
                }

                $topiccount = number_format((int) $forums_arr['topiccount']);
                $postcount = number_format((int) $forums_arr['postcount']);

                if (! $arr = $Cache?->get_value('forum_'.$forumid.'_last_replied_topic_content')) {
                    $lastTopic = ForumRepository::getLastTopicByForum((int) $forumid);
                    $arr = $lastTopic ? $lastTopic->toArray() : false;
                    $Cache?->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
                }

                if ($arr) {
                    $lastpostid = (int) $arr['lastpost'];
                    $post_arr = Forum::postRowWithContext($lastpostid);
                    $lastposterid = (int) ($post_arr['userid'] ?? 0);
                    $lastpostdate = Time::format($post_arr['added'] ?? '', true, false);
                    $lasttopicid = (int) $arr['id'];
                    $hlcolor = (int) $arr['hlcolor'];
                    $lasttopicdissubject = $lasttopicsubject = (string) ($arr['subject'] ?? '');
                    $max_length_of_topic_subject = 35;
                    $count_dispname = mb_strlen($lasttopicdissubject, 'UTF-8');
                    if ($count_dispname > $max_length_of_topic_subject) {
                        $lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject - 2, 'UTF-8').'..';
                    }
                    $lasttopic = $this->highlightTopic(htmlspecialchars($lasttopicdissubject), $hlcolor);

                    $lastpost = '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$lasttopicid.'&page=last#last').'" title="'.htmlspecialchars($lasttopicsubject).'">'.$lasttopic.'</a><br />'.$lastpostdate.'&nbsp;|&nbsp;'.UserDisplay::username($lastposterid);

                    $lastreadpost = $this->getLastReadPostId($lasttopicid, $curUser);

                    if ($lastreadpost >= $lastpostid) {
                        $img = $this->getTopicImage('read', $lang);
                    } else {
                        $img = $this->getTopicImage('unread', $lang);
                    }
                } else {
                    $lastpost = 'N/A';
                    $img = $this->getTopicImage('read', $lang);
                }
                $posttodaycount = $Cache?->get_value('forum_'.$forumid.'_post_'.$todayDate.'_count');
                if ($posttodaycount == '') {
                    $posttodaycount = ForumRepository::getForumTodayPostCount((int) $forumid, date('Y-m-d'));
                    $Cache?->cache_value('forum_'.$forumid.'_post_'.$todayDate.'_count', $posttodaycount, 1800);
                }
                if ($posttodaycount > 0) {
                    $posttoday = '&nbsp;&nbsp;('.($lang['text_today'] ?? '').'<b><font class="new">'.$posttodaycount.'</font></b>)';
                } else {
                    $posttoday = '';
                }
                echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img.'</td><td class="embedded"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><font class="big"><b>'.$forumname.'</b></font></a>'.$posttoday.
                '<br />'.$forumdescription.'</td></tr></table></td><td class="rowfollow" align="center" width="1%">'.$topiccount.'</td><td class="rowfollow" align="center" width="1%">'.$postcount.'</td>'.
                '<td class="rowfollow nowrap" align="left">'.$lastpost.'</td><td class="rowfollow" align="left">'.$forummoderators."</td></tr>\n";
            }
        }
        echo '</table>';
        if ($showforumstatsMain == 'yes') {
            echo $this->forumStats($lang, $todayDate);
        }

        return ['html' => (string) ob_get_clean()];
    }

    /**
     * Render the forum stats block.
     *
     * @param  array<string, mixed>  $lang
     */
    private function forumStats(array $lang, string $todayDate): string
    {
        $Cache = app(LegacyRedisCache::class);

        if (! $activeforumuser_num = $Cache?->get_value('active_forum_user_count')) {
            $activeforumuser_num = ForumRepository::getActiveForumUserCount();
            $Cache?->cache_value('active_forum_user_count', $activeforumuser_num, 300);
        }
        if ($activeforumuser_num) {
            $forumusers = ($lang['text_there'] ?? '').Strings::isOrAre((int) $activeforumuser_num).'<b>'.$activeforumuser_num.'</b>'.($lang['text_online_user'] ?? '').Strings::addS((int) $activeforumuser_num).($lang['text_in_forum_now'] ?? '');
        } else {
            $forumusers = ($lang['text_no_active_users'] ?? '');
        }

        ob_start();
        ?>
<h2 align="left"><?php echo $lang['text_stats'] ?? '' ?></h2>
<table width="100%"><tr><td class="text">
<?php
        if (! $postcount = $Cache?->get_value('total_posts_count')) {
            $postcount = ForumRepository::getTotalPostsCount();
            $Cache?->cache_value('total_posts_count', $postcount, 96400);
        }
        if (! $topiccount = $Cache?->get_value('total_topics_count')) {
            $topiccount = ForumRepository::getTotalTopicsCount();
            $Cache?->cache_value('total_topics_count', $topiccount, 96500);
        }
        if (! $todaypostcount = $Cache?->get_value('today_'.$todayDate.'_posts_count')) {
            $todaypostcount = ForumRepository::getTodayPostsCount($todayDate);
            $Cache?->cache_value('today_'.$todayDate.'_posts_count', $todaypostcount, 700);
        }
        echo ($lang['text_our_members_have'] ?? '').'<b>'.$postcount.'</b>'.($lang['text_posts_in_topics'] ?? '').'<b>'.$topiccount.'</b>'.($lang['text_in_topics'] ?? '').'<b><font class="new">'.$todaypostcount.'</font></b>'.($lang['text_new_post'] ?? '').Strings::addS((int) $todaypostcount).($lang['text_posts_today'] ?? '').'<br /><br />';
        echo $forumusers;
        ?>
</td></tr></table>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Mark all topics as read for the current user.
     */
    private function catchUp(): void
    {
        $CURUSER = (array) (app(CurrentUser::class)->get() ?? []);
        $Cache = app(LegacyRedisCache::class);

        if (! $CURUSER) {
            return;
        }
        ForumRepository::clearReadPosts((int) $CURUSER['id']);
        $Cache?->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
        $lastpostid = ForumRepository::getLastPostId();
        if ($lastpostid) {
            $CURUSER['last_catchup'] = $lastpostid;
            ForumRepository::updateLastCatchup((int) $CURUSER['id'], (int) $lastpostid);
        }
    }

    /**
     * @param  array<string, mixed>  $lang
     */
    private function checkWhetherExist(int $id, string $place, array $lang): void
    {
        LegacyResponse::assertId($id, true);
        switch ($place) {
            case 'forum':
                if (! ForumRepository::forumExists((int) $id)) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_forum_id'] ?? '');
                }
                break;

            case 'topic':
                $forumid = ForumRepository::topicExists((int) $id);
                if (! $forumid) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_bad_topic_id'] ?? '');
                }
                $this->checkWhetherExist((int) $forumid, 'forum', $lang);
                break;

            case 'post':
                $topicid = ForumRepository::postExists((int) $id);
                if (! $topicid) {
                    LegacyResponse::abort($lang['std_error'] ?? '', $lang['std_no_post_id'] ?? '');
                }
                $this->checkWhetherExist((int) $topicid, 'topic', $lang);
                break;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getForumRow(int $forumid = 0): ?array
    {
        $Cache = app(LegacyRedisCache::class);
        if (! $forums = $Cache?->get_value('forums_list')) {
            $forums = ForumRepository::getForumsList();
            $Cache?->cache_value('forums_list', $forums, 86400);
        }
        if (! $forumid) {
            return $forums;
        }

        return $forums[$forumid] ?? null;
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function getLastReadPostId(int $topicid, array $curUser): int
    {
        $Cache = app(LegacyRedisCache::class);
        static $ret = null;
        if (! $ret && ! $ret = $Cache?->get_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list')) {
            $ret = ForumRepository::getLastReadPosts((int) ($curUser['id'] ?? 0));
            if ($ret !== null) {
                $Cache?->cache_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list', $ret, 900);
            } else {
                $Cache?->cache_value('user_'.($curUser['id'] ?? 0).'_last_read_post_list', 'no record', 900);
            }
        }
        if (is_array($ret) && (isset($ret[$topicid])) && (int) ($curUser['last_catchup'] ?? 0) < (int) $ret[$topicid]) {
            return (int) $ret[$topicid];
        } elseif ((int) ($curUser['last_catchup'] ?? 0)) {
            return (int) $curUser['last_catchup'];
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $lang
     */
    private function getTopicImage(string $status, array $lang): string
    {
        switch ($status) {
            case 'read':
                return '<img class="unlocked" src="pic/trans.gif" alt="read" title="'.($lang['title_read'] ?? '').'" />';
            case 'unread':
                return '<img class="unlockednew" src="pic/trans.gif" alt="unread" title="'.($lang['title_unread'] ?? '').'" />';
            case 'locked':
                return '<img class="locked" src="pic/trans.gif" alt="locked" title="'.($lang['title_locked'] ?? '').'" />';
            case 'lockednew':
                return '<img class="lockednew" src="pic/trans.gif" alt="lockednew" title="'.($lang['title_locked_new'] ?? '').'" />';
        }

        return '';
    }

    private function highlightTopic(string $subject, int $hlcolor): string
    {
        $colorname = Palette::forumHighlight($hlcolor);
        if ($colorname) {
            $subject = '<b><font color="'.$colorname.'">'.$subject.'</font></b>';
        }

        return $subject;
    }

    private function highlightColorOptions(string $selectColorLabel): string
    {
        $colors = [
            1 => 'Black', 2 => 'Sienna', 3 => 'Dark Olive Green', 4 => 'Dark Green',
            5 => 'Dark Slate Blue', 6 => 'Navy', 7 => 'Indigo', 8 => 'Dark Slate Gray',
            9 => 'Dark Red', 10 => 'Dark Orange', 11 => 'Olive', 12 => 'Green',
            13 => 'Teal', 14 => 'Blue', 15 => 'Slate Gray', 16 => 'Dim Gray',
            17 => 'Red', 18 => 'Sandy Brown', 19 => 'Yellow Green', 20 => 'Sea Green',
            21 => 'Medium Turquoise', 22 => 'Royal Blue', 23 => 'Purple', 24 => 'Gray',
            25 => 'Magenta', 26 => 'Orange', 27 => 'Yellow', 28 => 'Lime',
            29 => 'Cyan', 30 => 'Deep Sky Blue', 31 => 'Dark Orchid', 32 => 'Silver',
            33 => 'Pink', 34 => 'Wheat', 35 => 'Lemon Chiffon', 36 => 'Pale Green',
            37 => 'Pale Turquoise', 38 => 'Light Blue', 39 => 'Plum', 40 => 'White',
        ];
        $cssNames = [
            1 => 'black', 2 => 'sienna', 3 => 'darkolivegreen', 4 => 'darkgreen',
            5 => 'darkslateblue', 6 => 'navy', 7 => 'indigo', 8 => 'darkslategray',
            9 => 'darkred', 10 => 'darkorange', 11 => 'olive', 12 => 'green',
            13 => 'teal', 14 => 'blue', 15 => 'slategray', 16 => 'dimgray',
            17 => 'red', 18 => 'sandybrown', 19 => 'yellowgreen', 20 => 'seagreen',
            21 => 'mediumturquoise', 22 => 'royalblue', 23 => 'purple', 24 => 'gray',
            25 => 'magenta', 26 => 'orange', 27 => 'yellow', 28 => 'lime',
            29 => 'cyan', 30 => 'deepskyblue', 31 => 'darkorchid', 32 => 'silver',
            33 => 'pink', 34 => 'wheat', 35 => 'lemonchiffon', 36 => 'palegreen',
            37 => 'paleturquoise', 38 => 'lightblue', 39 => 'plum', 40 => 'white',
        ];
        $out = "<option value='0'>".$selectColorLabel."</option>\n";
        foreach ($colors as $value => $name) {
            $out .= "<option style='background-color: ".$cssNames[$value]."' value=\"".$value.'">'.$name."</option>\n";
        }

        return $out;
    }
}
