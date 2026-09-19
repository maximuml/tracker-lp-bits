<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Enums\UserTimeType;
use App\Repositories\TopicRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\LegacyYesNo;
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
        private readonly Globals $globals,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly TopicRepository $topicRepository,
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Build the view-forum section.
     *
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewForum(array $curUser, Request $request, int $topicsperpage, int $postsperpage): array
    {
        $forumid = (int) (request()->query('forumid') ?? 0);
        LegacyResponse::assertId($forumid, true);
        $userid = (int) ($curUser['id'] ?? 0);

        $row = $this->index->getForumRow($forumid);
        if (! $row) {
            Log::writeWithContext('User '.($curUser['username'] ?? '').','.($curUser['ip'] ?? '')." is trying to visit forum that doesn't exist", 'mod');
            LegacyResponse::abort(__('legacy/forums.std_forum_error'), __('legacy/forums.std_forum_not_found'));
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

        $topicResult = $this->topicRepository->getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, 0, 0);
        $num = (int) $topicResult['count'];

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($topicsperpage, $num, '?'.'action=viewforum&forumid='.$forumid.$addparam.'&');
        $topicResult = $this->topicRepository->getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, (int) $offset, (int) $perpage);
        $topicRows = $topicResult['rows'];
        $numtopics = $topicRows->count();

        $SITENAME = (string) $this->globals->get('SITENAME', '');
        $enabletooltipTweak = (string) $this->globals->get('enabletooltip_tweak', '');

        ob_start();
        echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.(__('legacy/forums.text_forums')).'</a>--><a class="faqlink" href="'.htmlspecialchars('forums.php?action=viewforum&forumid='.$forumid).'">'.$forumname."</a></h1>\n";
        echo '<br />';
        $maypost = UserDisplay::currentClass() >= (int) ($row['minclasswrite'] ?? 0) && UserDisplay::currentClass() >= (int) ($row['minclasscreate'] ?? 0) && LegacyYesNo::isYes($curUser['forumpost'] ?? null);

        if (! $maypost) {
            echo '<p><i>'.(__('legacy/forums.text_unpermitted_starting_new_topics'))."</i></p>\n";
        }

        echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
        echo '<td class="embedded" width="90%">';
        echo $forummoderators ? '&nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="'.(__('legacy/forums.col_moderator')).'">&nbsp;'.$forummoderators : '';
        echo '</td><td class="embedded nowrap" width="1%">';
        if ($maypost) {
            echo '<a href="'.htmlspecialchars('?action=newtopic&forumid='.$forumid).'"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="'.(__('legacy/forums.title_new_topic')).'" /></a>&nbsp;&nbsp;';
        }
        echo '</td>';
        echo "</tr></table>\n";
        if ($numtopics > 0) {
            echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';

            $sortToggleFirst = (((request()->query('sort') !== null)) && request()->query('sort') == 'firstpostdesc') ? 'firstpostasc' : 'firstpostdesc';
            $sortToggleFirstTitle = (((request()->query('sort') !== null)) && request()->query('sort') == 'firstpostdesc') ? (__('legacy/forums.title_order_topic_asc')) : (__('legacy/forums.title_order_topic_desc'));
            $sortToggleLast = (((request()->query('sort') !== null)) && request()->query('sort') == 'lastpostasc') ? 'lastpostdesc' : 'lastpostasc';
            $sortToggleLastTitle = (((request()->query('sort') !== null)) && request()->query('sort') == 'lastpostasc') ? (__('legacy/forums.title_order_post_desc')) : (__('legacy/forums.title_order_post_asc'));

            echo '<tr><td class="colhead" align="center" width="99%">'.(__('legacy/forums.col_topic')).'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.$sortToggleFirst).'" title="'.$sortToggleFirstTitle.'">'.(__('legacy/forums.col_author')).'</a></td><td class="colhead" align="center">'.(__('legacy/forums.col_replies')).'/'.(__('legacy/forums.col_views')).'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.$sortToggleLast).'" title="'.$sortToggleLastTitle.'">'.(__('legacy/forums.col_last_post'))."</a></td>\n";

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

                if (! $posts = $this->legacyRedisCache?->get_value('topic_'.$topicid.'_post_count')) {
                    $posts = $this->postRepository->countTopicPosts((int) $topicid);
                    $this->legacyRedisCache?->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
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
                if ($enabletooltipTweak == 'yes' && ! LegacyYesNo::isNo($curUser['showlastpost'] ?? null)) {
                    if (($curUser['timetype'] ?? 1) != UserTimeType::TIMEALIVE->value) {
                        $lastposttime = (__('legacy/forums.text_at_time')).($arr['added'] ?? '');
                    } else {
                        $lastposttime = (__('legacy/forums.text_blank')).Time::format($arr['added'] ?? '', true, false, true);
                    }
                    $lptext = Format::formatComment(mb_substr((string) ($arr['body'] ?? ''), 0, 100, 'UTF-8').(mb_strlen((string) ($arr['body'] ?? ''), 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                    $lastpost_tooltip[$counter]['id'] = 'lastpost_'.$counter;
                    $lastpost_tooltip[$counter]['content'] = (__('legacy/forums.text_last_posted_by')).$lpusername.$lastposttime.'<br />'.$lptext;
                    $onmouseover = ' data-domtt-src="'.$lastpost_tooltip[$counter]['id'].'"';
                }

                $arr = Forum::postRowWithContext((int) $topicarr['firstpost']);
                $fpuserid = (int) ($arr['userid'] ?? 0);
                $fpauthor = UserDisplay::username((int) ($arr['userid'] ?? 0));

                $subject = ($sticky ? '<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.(__('legacy/forums.title_sticky')).'" />&nbsp;&nbsp;' : '').'<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid).'" '.$onmouseover.'>'.$this->index->highlightTopic(Format::highlight($search, htmlspecialchars((string) $topicarr['subject'])), $hlcolor).'</a>'.$topicpages;
                $lastpostread = $this->index->getLastReadPostId($topicid, $curUser);

                if ($lastpostread >= $lppostid) {
                    $img = $this->index->getTopicImage($locked ? 'locked' : 'read');
                } else {
                    $img = $this->index->getTopicImage($locked ? 'lockednew' : 'unread');
                    if ($lastpostread != (int) ($curUser['last_catchup'] ?? 0)) {
                        $subject .= '&nbsp;&nbsp;<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid.'&page=p'.$lastpostread.'#pid'.$lastpostread).'" title="'.(__('legacy/forums.title_jump_to_unread')).'"><span class="small new"><b>'.(__('legacy/forums.text_new')).'</b></span></a>';
                    }
                }

                $topictime = substr((string) ($arr['added'] ?? ''), 0, 10);
                if (strtotime((string) ($arr['added'] ?? '')) + 86400 > (int) (defined('TIMENOW') ? constant('TIMENOW') : time())) {
                    $topictime = '<span class="new small">'.$topictime.'</span>';
                } else {
                    $topictime = '<span class="small nx-color-gray">'.$topictime.'</span>';
                }

                echo '<tr><td class="rowfollow" align="left"><table border="0" cellspacing="0" cellpadding="0"><tr>'.
                '<td class="embedded">'.$img.
                "</td><td class=\"embedded\" align=\"left\">\n".
                $subject.'</td></tr></table></td><td class="rowfollow" align="center">'.UserDisplay::username($fpuserid).'<br />'.$topictime.'</td><td class="rowfollow" align="center">'.$replies.' / <span class="nx-color-gray">'.$views."</span></td>\n".
                '<td class="rowfollow nowrap" align="center">'.$lpadded.'<br />'.$lpusername."</td>\n";

                echo "</tr>\n";
                $counter++;
            }

            echo "<tr><td align=\"left\">\n";
            echo '<form method="get" action="forums.php"><b>'.(__('legacy/forums.text_fast_search')).'</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="'.$forumid.'" /><input type="text" name="search" />&nbsp;<input type="submit" value="'.(__('legacy/forums.text_go')).'" /></form>';
            echo '</td>';
            ?>
<td align="left" colspan="3">
<span id="order"><span><b><?php echo __('legacy/forums.text_order') ?></b></span>
<span id="orderlist" class="dropmenu nx-hidden"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=firstpostdesc"><?php echo __('legacy/forums.text_topic_desc') ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=firstpostasc"><?php echo __('legacy/forums.text_topic_asc') ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=lastpostdesc"><?php echo __('legacy/forums.text_post_desc') ?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam ?>&amp;sort=lastpostasc"><?php echo __('legacy/forums.text_post_asc') ?></a></li>
</ul>
</span>
</span>
</td>
<?php
            echo '</tr></table>'.$pagerbottom;
            if ($enabletooltipTweak == 'yes' && ! LegacyYesNo::isNo($curUser['showlastpost'] ?? null)) {
                echo Html::tooltipContainer($lastpost_tooltip, 400);
            }
        } else {
            echo '<p>'.(__('legacy/forums.text_no_topics_found')).'</p>';
        }

        return [
            'html' => SafeHtml::fromTrustedHtml((string) ob_get_clean()),
            'forumid' => $forumid,
            'forumname' => $forumname,
        ];
    }

    /**
     * Build the view-unread-posts section.
     *
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewUnread(array $curUser): array
    {
        $userid = (int) ($curUser['id'] ?? 0);
        $beforepostid = (int) (request()->query('beforepostid') ?? 0);
        $maxresults = 25;
        $lastCatchup = (int) ($curUser['last_catchup'] ?? 0);
        $unreadTopics = $this->topicRepository->getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

        $SITENAME = (string) $this->globals->get('SITENAME', '');

        ob_start();
        echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.(__('legacy/forums.text_forums')).'</a>-->'.(__('legacy/forums.text_topics_with_unread_posts')).'</h1>';

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
                echo '<tr><td class="colhead" align="left">'.(__('legacy/forums.col_topic')).'</td><td class="colhead" align="left">'.(__('legacy/forums.col_forum'))."</td></tr>\n";
            }
            echo '<tr><td class="rowfollow" align="left"><table border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">'.
            $this->index->getTopicImage('unread').'</td><td class="embedded">'.
            '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.($lastpostread > 0 && $lastpostread != (int) ($curUser['last_catchup'] ?? 0) ? '&page=p'.$lastpostread.'#pid'.$lastpostread : '')).'">'.$this->index->highlightTopic(htmlspecialchars((string) $arr['subject']), (int) $arr['hlcolor']).
            '</a></td></tr></table></td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><b>'.$forumname."</b></a></td></tr>\n";
        }
        if ($n > 0) {
            echo "</table>\n";
            echo '<table border="0" class="main" cellspacing="0" cellpadding="5" width="1%"><tr><td class="embedded"><form method="get" action="?"><input type="hidden" name="catchup" value="1" /><input type="submit" value="'.(__('legacy/forums.text_catch_up')).'" class="btn" /></form></td>';
            if ($n > $maxresults) {
                echo '<td class="embedded"><form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="'.$topiclastpost.'" /><input type="submit" value="'.(__('legacy/forums.submit_show_more')).'" class="btn" /></form></td>';
            }
            echo '</tr></table>';
        } else {
            echo '<p>'.(__('legacy/forums.text_nothing_found')).'</p>';
        }

        return ['html' => SafeHtml::fromTrustedHtml((string) ob_get_clean())];
    }

    /**
     * Build the forum search section.
     *
     * @return array<string, mixed>
     */
    public function buildSearch(int $topicsperpage): array
    {
        $error = true;
        $found = '';
        $keywords = htmlspecialchars(trim((string) (request()->query('keywords') ?? '')));
        if ($keywords != '') {
            $searchResult = $this->postRepository->searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), 0, 0);
            $hits = (int) $searchResult['hits'];
            if ($hits) {
                $error = false;
                $found = '[<b><span class="striking"> '.(__('legacy/forums.text_found')).$hits.(__('legacy/forums.text_num_posts')).' </span></b>]';
            }
        }

        ob_start();
        ?>
<div class="search">
	<div class="search_title"><?php echo __('legacy/forums.text_search_on_forum') ?> <?php echo $error && $keywords != '' ? '[<b><span class="striking"> '.(__('legacy/forums.text_nothing_found')).'</span></b> ]' : $found ?></div>
	<div>
		<form method="get" action="forums.php" id="search_form">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td valign="top"><?php echo __('legacy/forums.text_by_keyword') ?></td>
		</tr>
		<tr>
		<td valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords ?>" /></td>
			<td valign="top"><input name="image" type="image" src="<?php echo Forum::picFolderWithContext() ?>/search_button.gif" alt="Search" /></td>
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
            $searchResult = $this->postRepository->searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), (int) $offset, (int) $perpage);
            $posts = $searchResult['rows'];

            echo $pagertop;
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n";
            echo '<tr><td class="colhead" align="center">'.(__('legacy/forums.col_post')).'</td><td class="colhead" align="center" width="70%">'.(__('legacy/forums.col_topic')).'</td><td class="colhead" align="left">'.(__('legacy/forums.col_forum')).'</td><td class="colhead" align="left">'.(__('legacy/forums.col_posted_by'))."</td></tr>\n";

            foreach ($posts as $post) {
                $post = (array) $post;
                echo '<tr><td class="rowfollow" align="center" width="1%">'.$post['id'].'</td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewtopic&topicid='.$post['topicid'].'&highlight='.rawurlencode($keywords).'&page=p'.$post['id'].'#pid'.$post['id']).'">'.$this->index->highlightTopic(Format::highlight($keywords, htmlspecialchars((string) $post['subject'])), (int) $post['hlcolor']).'</a></td><td class="rowfollow nowrap" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$post['forumid']).'"><b>'.htmlspecialchars((string) $post['forumname']).'</b></a></td><td class="rowfollow nowrap" align="left">'.Time::format($post['added'], true, false).'&nbsp;|&nbsp;'.UserDisplay::username((int) $post['userid'])."</td></tr>\n";
            }

            echo "</table>\n";
            echo $pagerbottom;
        }

        return ['html' => SafeHtml::fromTrustedHtml((string) ob_get_clean())];
    }
}
