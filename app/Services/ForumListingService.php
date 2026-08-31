<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForumRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

/**
 * Builds the forum listing sections (view-forum, view-unread, search)
 * for the forums page.
 */
final class ForumListingService
{
    public function __construct(
        private readonly ForumIndexService $index,
    ) {}

    /**
     * Build the view-forum section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewForum(array $lang, array $curUser, Request $request, int $topicsperpage, int $postsperpage): array
    {
        $Cache = app(LegacyRedisCache::class);
        $forumid = (int) (request()->query('forumid') ?? 0);
        LegacyResponse::assertId($forumid, true);
        $userid = (int) ($curUser['id'] ?? 0);

        $row = $this->index->getForumRow($forumid);
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

        $topicResult = app(ForumRepository::class)->getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, 0, 0);
        $num = (int) $topicResult['count'];

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($topicsperpage, $num, '?'.'action=viewforum&forumid='.$forumid.$addparam.'&');
        $topicResult = app(ForumRepository::class)->getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, (int) $offset, (int) $perpage);
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
                $locked = (bool) $topicarr['locked'];
                $sticky = $topicarr['sticky'] == 1;
                $hlcolor = (int) $topicarr['hlcolor'];

                if (! $posts = $Cache?->get_value('topic_'.$topicid.'_post_count')) {
                    $posts = app(ForumRepository::class)->countTopicPosts((int) $topicid);
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

                $subject = ($sticky ? '<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.($lang['title_sticky'] ?? '').'" />&nbsp;&nbsp;' : '').'<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid).'" '.$onmouseover.'>'.$this->index->highlightTopic(Format::highlight($search, htmlspecialchars((string) $topicarr['subject'])), $hlcolor).'</a>'.$topicpages;
                $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

                if ($lastpostread >= $lppostid) {
                    $img = $this->index->getTopicImage($locked ? 'locked' : 'read', $lang);
                } else {
                    $img = $this->index->getTopicImage($locked ? 'lockednew' : 'unread', $lang);
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
    public function buildViewUnread(array $lang, array $curUser): array
    {
        $userid = (int) ($curUser['id'] ?? 0);
        $beforepostid = (int) (request()->query('beforepostid') ?? 0);
        $maxresults = 25;
        $lastCatchup = (int) ($curUser['last_catchup'] ?? 0);
        $unreadTopics = app(ForumRepository::class)->getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

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

            $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

            if ($lastpostread >= $topiclastpost) {
                continue;
            }

            $forumid = (int) $arr['forumid'];
            $a = $this->index->getForumRow($forumid);
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
            $this->index->getTopicImage('unread', $lang).'</td><td class="embedded">'.
            '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.($lastpostread > 0 && $lastpostread != (int) ($curUser['last_catchup'] ?? 0) ? '&page=p'.$lastpostread.'#pid'.$lastpostread : '')).'">'.$this->index->highlightTopic(htmlspecialchars((string) $arr['subject']), (int) $arr['hlcolor']).
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
    public function buildSearch(array $lang, int $topicsperpage): array
    {
        $error = true;
        $found = '';
        $keywords = htmlspecialchars(trim((string) (request()->query('keywords') ?? '')));
        if ($keywords != '') {
            $searchResult = app(ForumRepository::class)->searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), 0, 0);
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
            $searchResult = app(ForumRepository::class)->searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), (int) $offset, (int) $perpage);
            $posts = $searchResult['rows'];

            echo $pagertop;
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n";
            echo '<tr><td class="colhead" align="center">'.($lang['col_post'] ?? '').'</td><td class="colhead" align="center" width="70%">'.($lang['col_topic'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_forum'] ?? '').'</td><td class="colhead" align="left">'.($lang['col_posted_by'] ?? '')."</td></tr>\n";

            foreach ($posts as $post) {
                $post = (array) $post;
                echo '<tr><td class="rowfollow" align="center" width="1%">'.$post['id'].'</td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewtopic&topicid='.$post['topicid'].'&highlight='.rawurlencode($keywords).'&page=p'.$post['id'].'#pid'.$post['id']).'">'.$this->index->highlightTopic(Format::highlight($keywords, htmlspecialchars((string) $post['subject'])), (int) $post['hlcolor']).'</a></td><td class="rowfollow nowrap" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$post['forumid']).'"><b>'.htmlspecialchars((string) $post['forumname']).'</b></a></td><td class="rowfollow nowrap" align="left">'.Time::format($post['added'], true, false).'&nbsp;|&nbsp;'.UserDisplay::username((int) $post['userid'])."</td></tr>\n";
            }

            echo "</table>\n";
            echo $pagerbottom;
        }

        return ['html' => (string) ob_get_clean()];
    }
}
