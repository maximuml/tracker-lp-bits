<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Ratio;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\Request;

/**
 * Builds the view-topic section (single topic with paginated posts
 * and mod toolbox) for the forums page.
 */
final class ForumTopicViewService
{
    public function __construct(
        private readonly ForumIndexService $index,
    ) {}

    /**
     * Build the view-topic section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewTopic(array $lang, array $curUser, int $userId, Request $request, int $postsperpage): array
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

        $topic = app(ForumRepository::class)->getTopic((int) $topicid);
        if (! $topic) {
            LegacyResponse::abort($lang['std_forum_error'] ?? '', $lang['std_topic_not_found'] ?? '');

            return [];
        }
        $arr = $topic->toArray();

        $forumid = (int) $arr['forumid'];
        $locked = (bool) $arr['locked'];
        $orgsubject = (string) $arr['subject'];
        $subject = htmlspecialchars((string) $arr['subject']);
        if ($highlight) {
            $subject = Format::highlight($highlight, $orgsubject);
        }
        $sticky = $arr['sticky'] == 1;
        $hlcolor = (int) $arr['hlcolor'];
        $views = (int) $arr['views'];
        $basePosterid = (int) $arr['userid'];

        $row = $this->index->getForumRow($forumid);
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

        app(ForumRepository::class)->incrementTopicViews((int) $topicid);

        $postcount = app(ForumRepository::class)->countTopicPosts((int) $topicid, $authorid ?: null);
        if (! $authorid) {
            $Cache?->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);
        }

        $pagerarr = [];
        $perpage = $postsperpage;
        $pages = (int) ceil($postcount / max(1, $perpage));

        if ((isset($page[0])) && $page[0] == 'p') {
            $findpost = substr($page, 1);
            $postIds = app(ForumRepository::class)->getTopicPostIds((int) $topicid, $authorid ?: null);
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

        $postRows = app(ForumRepository::class)->getTopicPosts((int) $topicid, $authorid ?: null, (int) $offset, (int) $perpage);
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
        $userInfoArr = app(ForumRepository::class)->getUsersByIds($uidArr, $neededColumns);
        $pn = 0;
        $lpr = $this->index->getLastReadPostId($topicid, $curUser);

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
                $forumposts = app(ForumRepository::class)->countUserPosts((int) $posterid);
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
                    app(ForumRepository::class)->markPostRead((int) $userId, (int) $topicid, (int) $postid, (int) ($curUser['last_catchup'] ?? 0));
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
            echo '<input type="hidden" name="locked" value="'.($locked ? 0 : 1).'" /><input type="submit" class="medium" value="'.($locked ? ($lang['submit_unlock'] ?? '') : ($lang['submit_lock'] ?? ''))."\" /></form></td>\n";
            echo "<td class=\"embedded\"><form method=\"get\" action=\"?\">\n";
            echo "<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n";
            echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
            echo '<input type="hidden" name="forumid" value="'.$forumid."\" />\n";
            echo '<input type="submit" class="medium" value="'.($lang['submit_delete_topic'] ?? '')."\" /></form></td>\n";
            echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=movetopic&topicid='.$topicid)."\">\n".'&nbsp;'.($lang['text_move_thread_to'] ?? '').'&nbsp;<select class="med" name="forumid">';
            $forums = $this->index->getForumRow(0);
            foreach ($forums ?? [] as $arr) {
                if ($arr['id'] != $forumid && UserDisplay::currentClass() >= (int) $arr['minclasswrite']) {
                    echo '<option value="'.$arr['id'].'">'.htmlspecialchars((string) $arr['name'])."</option>\n";
                }
            }
            echo '</select> <input type="submit" class="medium" value="'.($lang['submit_move'] ?? '').'" /></form></td>';
            echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=hltopic&topicid='.$topicid)."\">\n".'&nbsp;'.($lang['text_highlight_topic'] ?? '').'&nbsp;<select class="med" name="color">';
            echo $this->index->highlightColorOptions((string) ($lang['select_color'] ?? ''));
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
}
