<?php

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Frame;
use App\Support\Hooks;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\Palette;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($Cache)) {
    $Cache = SupportContext::getCache();
}
if (! isset($BASEURL)) {
    $BASEURL = SupportContext::getGlobal('BASEURL', '');
}
if (! isset($lang_forums)) {
    $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
}
// The original procedural page relies on loose PHP variable/array-key
// handling. Keep the same runtime error level so that undefined indices
// and null-to-string coercions behave as they did outside Laravel.

$__server_REQUEST_URI = SupportContext::getServerValue('REQUEST_URI');
// ------------- start: functions ------------------//
// print forum stats
if (! function_exists('forum_stats')) {
    function forum_stats()
    {
        $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
        $Cache = SupportContext::getCache();
        $today_date = SupportContext::getGlobal('today_date', '');

        if (! $activeforumuser_num = $Cache->get_value('active_forum_user_count')) {
            $activeforumuser_num = ForumRepository::getActiveForumUserCount();
            $Cache->cache_value('active_forum_user_count', $activeforumuser_num, 300);
        }
        if ($activeforumuser_num) {
            $forumusers = $lang_forums['text_there'].Strings::isOrAre($activeforumuser_num).'<b>'.$activeforumuser_num.'</b>'.$lang_forums['text_online_user'].Strings::addS($activeforumuser_num).$lang_forums['text_in_forum_now'];
        } else {
            $forumusers = $lang_forums['text_no_active_users'];
        }
        ?>
<h2 align="left"><?php echo $lang_forums['text_stats'] ?></h2>
<table width="100%"><tr><td class="text">
<?php
            if (! $postcount = $Cache->get_value('total_posts_count')) {
                $postcount = ForumRepository::getTotalPostsCount();
                $Cache->cache_value('total_posts_count', $postcount, 96400);
            }
        if (! $topiccount = $Cache->get_value('total_topics_count')) {
            $topiccount = ForumRepository::getTotalTopicsCount();
            $Cache->cache_value('total_topics_count', $topiccount, 96500);
        }
        if (! $todaypostcount = $Cache->get_value('today_'.$today_date.'_posts_count')) {
            $todaypostcount = ForumRepository::getTodayPostsCount($today_date);
            $Cache->cache_value('today_'.$today_date.'_posts_count', $todaypostcount, 700);
        }
        echo $lang_forums['text_our_members_have'].'<b>'.$postcount.'</b>'.$lang_forums['text_posts_in_topics'].'<b>'.$topiccount.'</b>'.$lang_forums['text_in_topics'].'<b><font class="new">'.$todaypostcount.'</font></b>'.$lang_forums['text_new_post'].Strings::addS($todaypostcount).$lang_forums['text_posts_today'].'<br /><br />';
        echo $forumusers;
        ?>
</td></tr></table>
<?php
    }
}

// set all topics as read
if (! function_exists('catch_up')) {
    function catch_up()
    {
        $CURUSER = SupportContext::getUser() ?? [];
        $Cache = SupportContext::getCache();

        if (! $CURUSER) {
            return;
        }
        ForumRepository::clearReadPosts((int) $CURUSER['id']);
        $Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
        $lastpostid = ForumRepository::getLastPostId();
        if ($lastpostid) {
            $CURUSER['last_catchup'] = $lastpostid;
            ForumRepository::updateLastCatchup((int) $CURUSER['id'], $lastpostid);
        }
    }
}

// return image
if (! function_exists('get_topic_image')) {
    function get_topic_image($status = 'read')
    {
        $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
        switch ($status) {
            case 'read':
                return '<img class="unlocked" src="pic/trans.gif" alt="read" title="'.$lang_forums['title_read'].'" />';
                break;

            case 'unread':
                return '<img class="unlockednew" src="pic/trans.gif" alt="unread" title="'.$lang_forums['title_unread'].'" />';
                break;

            case 'locked':
                return '<img class="locked" src="pic/trans.gif" alt="locked" title="'.$lang_forums['title_locked'].'" />';
                break;

            case 'lockednew':
                return '<img class="lockednew" src="pic/trans.gif" alt="lockednew" title="'.$lang_forums['title_locked_new'].'" />';
                break;

        }
    }
}

if (! function_exists('highlight_topic')) {
    function highlight_topic($subject, $hlcolor = 0)
    {
        $colorname = Palette::forumHighlight($hlcolor);
        if ($colorname) {
            $subject = '<b><font color="'.$colorname.'">'.$subject.'</font></b>';
        }

        return $subject;
    }
}

if (! function_exists('check_whether_exist')) {
    function check_whether_exist($id, $place = 'forum')
    {
        $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
        LegacyResponse::assertId($id, true);
        switch ($place) {
            case 'forum':

                if (! ForumRepository::forumExists((int) $id)) {
                    LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_no_forum_id']);
                }
                break;

            case 'topic':

                $forumid = ForumRepository::topicExists((int) $id);
                if (! $forumid) {
                    LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_bad_topic_id']);
                }
                check_whether_exist($forumid, 'forum');
                break;

            case 'post':

                $topicid = ForumRepository::postExists((int) $id);
                if (! $topicid) {
                    LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_no_post_id']);
                }
                check_whether_exist($topicid, 'topic');
                break;

        }
    }
}

// update the last post of a topic
if (! function_exists('update_topic_last_post')) {
    function update_topic_last_post($topicid)
    {
        $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
        ForumRepository::updateTopicLastPost((int) $topicid);
    }
}

if (! function_exists('get_forum_row')) {
    function get_forum_row($forumid = 0)
    {
        $Cache = SupportContext::getCache();
        if (! $forums = $Cache->get_value('forums_list')) {
            $forums = ForumRepository::getForumsList();
            $Cache->cache_value('forums_list', $forums, 86400);
        }
        if (! $forumid) {
            return $forums;
        } else {
            return $forums[$forumid] ?? null;
        }
    }
}
if (! function_exists('get_last_read_post_id')) {
    function get_last_read_post_id($topicid)
    {
        $CURUSER = SupportContext::getUser() ?? [];
        $Cache = SupportContext::getCache();
        static $ret;
        if (! $ret && ! $ret = $Cache->get_value('user_'.$CURUSER['id'].'_last_read_post_list')) {
            $ret = ForumRepository::getLastReadPosts((int) $CURUSER['id']);
            if ($ret !== null) {
                $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', $ret, 900);
            } else {
                $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', 'no record', 900);
            }
        }
        if (is_array($ret) && (isset($ret[$topicid])) && $CURUSER['last_catchup'] < $ret[$topicid]) {
            return $ret[$topicid];
        } elseif ($CURUSER['last_catchup']) {
            return $CURUSER['last_catchup'];
        } else {
            return 0;
        }
    }
}

// -------- Inserts a compose frame
if (! function_exists('insert_compose_frame')) {
    function insert_compose_frame($id, $type = 'new')
    {
        $maxsubjectlength = SupportContext::getGlobal('maxsubjectlength');
        $CURUSER = SupportContext::getUser() ?? [];
        $lang_forums = (array) (SupportContext::getGlobal('lang_forums') ?? []);
        $hassubject = false;
        $subject = '';
        $body = '';
        echo "<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n";
        switch ($type) {
            case 'new':

                $forumname = ForumRepository::getForumName((int) $id) ?? '';
                $title = $lang_forums['text_new_topic_in'].' <a href="'.htmlspecialchars('?action=viewforum&forumid='.$id).'">'.htmlspecialchars($forumname).'</a> '.$lang_forums['text_forum'];
                $hassubject = true;
                break;

            case 'reply':

                $topicname = ForumRepository::getTopicSubject((int) $id) ?? '';
                $title = $lang_forums['text_reply_to_topic'].' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$id).'">'.htmlspecialchars($topicname).'</a> ';
                break;

            case 'quote':

                $post = ForumRepository::getPostForQuote((int) $id);
                if (! $post) {
                    LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_no_post_id']);
                }
                $topicid = $post['topicid'];
                $topicname = $post['topic_subject'] ?? '';
                $title = $lang_forums['text_reply_to_topic'].' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid).'">'.htmlspecialchars($topicname).'</a> ';
                $body = '[quote='.htmlspecialchars($post['username']).']'.htmlspecialchars(Input::unescape($post['body'])).'[/quote]';
                echo '<input type="hidden" name="postid" value="'.$id.'" />';
                $id = $topicid;
                $type = 'reply';
                break;

            case 'edit':

                $post = ForumRepository::getPostForEdit((int) $id);
                if (! $post) {
                    return;
                }
                $topicid = $post['topicid'];
                if ($post['is_first_post']) {
                    $subject = $post['topic_subject'] ?? '';
                    $hassubject = true;
                }
                $body = htmlspecialchars(Input::unescape($post['body']));
                $title = $lang_forums['text_edit_post'];
                break;

            default:

                return;

        }
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        echo '<input type="hidden" name="type" value="'.$type.'" />';
        Frame::composeBeginVoid($title, $type, $body, $hassubject, $subject);
        Frame::composeEndVoid();
        echo '</form>';
    }
}
// ------------- end: functions ------------------//
// ------------- start: Global variables ------------------//
$maxsubjectlength = 100;
$postsperpage = $CURUSER['postsperpage'];
if (! $postsperpage) {
    if (is_numeric($forumpostsperpage)) {
        $postsperpage = $forumpostsperpage;
    }// system-wide setting
    else {
        $postsperpage = 10;
    }
}
// get topics per page
$topicsperpage = $CURUSER['topicsperpage'];
if (! $topicsperpage) {
    if (is_numeric($forumtopicsperpage_main)) {
        $topicsperpage = $forumtopicsperpage_main;
    }// system-wide setting
    else {
        $topicsperpage = 20;
    }
}
$today_date = date('Y-m-d', TIMENOW);
SupportContext::setGlobal('maxsubjectlength', $maxsubjectlength);
SupportContext::setGlobal('postsperpage', $postsperpage);
SupportContext::setGlobal('topicsperpage', $topicsperpage);
SupportContext::setGlobal('today_date', $today_date);
// ------------- end: Global variables ------------------//

$action = htmlspecialchars(trim(SupportContext::getQuery('action') ?? ''));

// -------- Action: New topic
if ($action == 'newtopic') {
    $forumid = intval(SupportContext::getQuery('forumid') ?? 0);
    check_whether_exist($forumid, 'forum');
    insert_compose_frame($forumid, 'new');

    return;
}
if ($action == 'quotepost') {
    $postid = intval(SupportContext::getQuery('postid') ?? 0);
    check_whether_exist($postid, 'post');
    if (! Forum::canViewPost($CURUSER['id'], $postid)) {
        LegacyResponse::permissionDenied();
    }
    insert_compose_frame($postid, 'quote');

    return;
}

// -------- Action: Reply

if ($action == 'reply') {
    $topicid = intval(SupportContext::getQuery('topicid') ?? 0);
    check_whether_exist($topicid, 'topic');
    insert_compose_frame($topicid, 'reply');

    return;
}

// -------- Action: Edit post

if ($action == 'editpost') {
    $postid = intval(SupportContext::getQuery('postid') ?? 0);
    check_whether_exist($postid, 'post');

    $post = ForumRepository::getPostWithTopic((int) $postid);
    if (! $post) {
        LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_no_post_id']);
    }

    $locked = $post['locked'] == 'yes';

    $ismod = Forum::isModerator($postid, 'post');
    if (($CURUSER['id'] != $post['userid'] || $locked) && ! Permission::can(PermissionEnum::POST_MANAGE) && ! $ismod) {
        LegacyResponse::permissionDenied();
    }

    insert_compose_frame($postid, 'edit');

    return;
}

// -------- Action: View topic

if ($action == 'viewtopic') {
    $highlight = htmlspecialchars(trim(SupportContext::getQuery('highlight') ?? ''));

    $topicid = intval(SupportContext::getQuery('topicid') ?? 0);
    LegacyResponse::assertId($topicid, true);
    $page = SupportContext::getQuery('page') ?? 0;
    $authorid = intval(SupportContext::getQuery('authorid') ?? 0);
    if ($authorid) {
        $addparam = 'action=viewtopic&topicid='.$topicid.'&authorid='.$authorid;
    } else {
        $addparam = 'action=viewtopic&topicid='.$topicid;
    }
    $userid = $CURUSER['id'];

    // ------ Get topic info

    $topic = ForumRepository::getTopic((int) $topicid);
    if (! $topic) {
        LegacyResponse::abort($lang_forums['std_forum_error'], $lang_forums['std_topic_not_found']);
    }
    $arr = $topic->toArray();

    $forumid = $arr['forumid'];
    $locked = $arr['locked'] == 'yes';
    $orgsubject = $arr['subject'];
    $subject = htmlspecialchars($arr['subject']);
    if ($highlight) {
        $subject = Format::highlight($highlight, $orgsubject);
    }
    $sticky = $arr['sticky'] == 'yes';
    $hlcolor = $arr['hlcolor'];
    $views = $arr['views'];
    $forumid = $arr['forumid'];
    $base_posterid = $arr['userid'];

    $row = get_forum_row($forumid);
    // ------ Get forum name, moderators
    $forumname = $row['name'];
    $is_forummod = Forum::isModerator($forumid, 'forum');

    if (UserDisplay::currentClass() < $row['minclassread']) {
        LegacyResponse::abort($lang_forums['std_error'], $lang_forums['std_unpermitted_viewing_topic']);
    }
    if (((UserDisplay::currentClass() >= $row['minclasswrite'] && ! $locked) || Permission::can(PermissionEnum::POST_MANAGE) || $is_forummod) && $CURUSER['forumpost'] == 'yes') {
        $maypost = true;
    } else {
        $maypost = false;
    }

    // ------ Update hits column
    ForumRepository::incrementTopicViews((int) $topicid);

    // ------ Get post count
    $postcount = ForumRepository::countTopicPosts((int) $topicid, $authorid ?: null);
    if (! $authorid) {
        $Cache->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);
    }

    // ------ Make page menu

    $pagerarr = [];

    $perpage = $postsperpage;

    $pages = ceil($postcount / $perpage);

    if ((isset($page[0])) && $page[0] == 'p') {
        $findpost = substr($page, 1);
        $postIds = ForumRepository::getTopicPostIds((int) $topicid, $authorid ?: null);
        $i = array_search($findpost, $postIds);
        if ($i === false) {
            $i = 0;
        }
        $page = floor($i / $perpage);
    }
    if ($page === 'last') {
        $page = $pages - 1;
    } elseif ((isset($page))) {
        if ($page < 0) {
            $page = 0;
        } elseif ($page > $pages - 1) {
            $page = $pages - 1;
        }
    } else {
        if ($CURUSER['clicktopic'] == 'firstpage') {
            $page = 0;
        } else {
            $page = $pages - 1;
        }
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
        $pager = '<font class="gray"><b>&lt;&lt;'.$lang_forums['text_prev'].'</b></font>';
    } else {
        $pager = '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page - 1)).
        '"><b>&lt;&lt;'.$lang_forums['text_prev'].'</b></a>';
    }
    $pager .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    if ($page == $pages - 1) {
        $pager .= '<font class="gray"><b>'.$lang_forums['text_next']." &gt;&gt;</b></font>\n";
    } else {
        $pager .= '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page + 1)).
        '"><b>'.$lang_forums['text_next']." &gt;&gt;</b></a>\n";
    }

    $pagerstr = implode(' | ', $pagerarr);
    $pagertop = '<p align="center">'.$pager.'<br />'.$pagerstr."</p>\n";
    $pagerbottom = '<p align="center">'.$pagerstr.'<br />'.$pager."</p>\n";
    // ------ Get posts

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

    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>--><a class="faqlink" href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'">'.$forumname.'</a><b>--></b><span id="top">'.$subject.($locked ? '&nbsp;&nbsp;<b>[<font class="striking">'.$lang_forums['text_locked'].'</font>]</b>' : '')."</span></h1>\n";
    echo $pagertop;

    // ------ Print table

    echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
    echo '<td class="embedded" width="99%">&nbsp;&nbsp;'.$lang_forums['there_is'].'<b>'.$views.'</b>'.$lang_forums['hits_on_this_topic'];
    echo "</td>\n";
    echo '<td class="embedded nowrap" width="1%" align="right">';
    if ($maypost) {
        echo '<a href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="'.$lang_forums['title_reply_directly'].'" /></a>&nbsp;&nbsp;';
    }
    echo '</td>';
    echo "</tr></table>\n";
    Html::beginFrame();

    $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
    $userInfoArr = ForumRepository::getUsersByIds($uidArr, $neededColumns);
    $pn = 0;
    $lpr = get_last_read_post_id($topicid);

    // check if privacy protection enabled in this forum
    //	$protected_forums = Nexus\Database\NexusDB::remember("setting_protected_forum", 600, function () {
    //		return \App\Models\Setting::getByName('misc.protected_forum');
    //	});
    //
    //	if ($protected_forums and in_array(strval($forumid),explode(",",$protected_forums))){
    //		$protected_enabled=true;
    //	}else{
    //		$protected_enabled=false;
    //	}

    foreach ($allPosts as $arr) {
        $pn++;

        $postid = $arr['id'];
        $posterid = $arr['userid'];

        $added = Time::format($arr['added'], true, false);

        // ---- Get poster details

        //		$arr2 = get_user_row($posterid);
        $userInfo = $userInfoArr->get($posterid) ?: User::defaultUser();

        $arr2 = $userInfo->toArray();

        $uploaded = Format::size($arr2['uploaded']);
        $downloaded = Format::size($arr2['downloaded']);
        $ratio = Ratio::forUserId($arr2['id']);

        if (! $forumposts = $Cache->get_value('user_'.$posterid.'_post_count')) {
            $forumposts = ForumRepository::countUserPosts((int) $posterid);
            $Cache->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
        }

        $signature = ($CURUSER['signatures'] == 'yes' ? $arr2['signature'] : '');
        $avatar = ($CURUSER['avatars'] == 'yes' ? htmlspecialchars($arr2['avatar']) : '');

        $uclass = UserClass::imagePath($arr2['class']);
        $by = UserDisplay::username($posterid, false, true, true, false, false, true);

        if (! $avatar) {
            $avatar = 'pic/default_avatar.png';
        }

        if ($pn == $pc) {
            echo "<span id=\"last\"></span>\n";
            if ($postid > $lpr) {
                ForumRepository::markPostRead((int) $userid, (int) $topicid, (int) $postid, (int) ($CURUSER['last_catchup'] ?? 0));
                $Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
            }
        }

        echo '<div style="margin-top: 8pt; margin-bottom: 8pt;"><table id="pid'.$postid.'" border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td class="embedded" width="99%"><a href="'.htmlspecialchars('forums.php?action=viewtopic&topicid='.$topicid.'&page=p'.$postid.'#pid'.$postid).'">#'.$postid.'</a>&nbsp;&nbsp;<font color="gray">'.$lang_forums['text_by'].'</font>'.$by.'&nbsp;&nbsp;<font color="gray">'.$lang_forums['text_at'].'</font>'.$added;
        if (Validators::isId($arr['editedby'])) {
            echo '';
        }
        echo '&nbsp;&nbsp;<font color="gray">|</font>&nbsp;&nbsp;';
        if ($authorid) {
            echo '<a href="?action=viewtopic&topicid='.$topicid.'">'.$lang_forums['text_view_all_posts'].'</a>';
        } else {
            echo '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.'&authorid='.$posterid).'">'.$lang_forums['text_view_this_author_only'].'</a>';
        }
        echo '</td><td class="embedded nowrap" width="1%"><font class="big">'.$lang_forums['text_number'].'<b>'.($pn + $offset).'</b>'.$lang_forums['text_lou'].'&nbsp;&nbsp;</font><a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="'.$lang_forums['text_back_to_top'].'" /></a>&nbsp;&nbsp;</td></tr>';

        echo "</table></div>\n";

        echo "<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";

        $body = '<div id="pid'.$postid.'body" style="word-break: break-all;">';
        // hidden content applied to second or higher floor post (for whose user class below Ad , not poster , not mods ,not reply's author)
        //		if ($protected_enabled && $pn+$offset>1 && get_user_class()<UC_ADMINISTRATOR && $userid != $base_posterid && $posterid!=$userid && !$is_forummod){
        if ($pn + $offset > 1 && ! Forum::canViewPost($userid, $arr)) {
            // enable content protection
            $bodyContent = Format::formatComment($lang_forums['text_post_protected']);
            $canViewProtected = false;
        } else {
            // display normal content
            $bodyContent = Format::formatComment($arr['body']);
            $canViewProtected = true;
        }
        if ($highlight) {
            $bodyContent = Format::highlight($highlight, $bodyContent);
        }

        if (Validators::isId($arr['editedby'])) {
            $lastedittime = Time::format($arr['editdate'], true, false);
            $bodyContent .= '<br /><p><font class="small">'.$lang_forums['text_last_edited_by'].UserDisplay::username($arr['editedby']).$lang_forums['text_last_edit_at'].$lastedittime."</font></p>\n";
        }
        $bodyContent = Hooks::applyFilter('post_body', ...[$bodyContent, $arr, $allPosts]);
        $body .= $bodyContent.'</div>';
        if ($signature) {
            $body .= "<p style='vertical-align:bottom'><br />____________________<br />".Format::formatComment($signature, false, false, false, true, 500, true, false, 1, 200).'</p>';
        }

        $stats = '<br />'.'&nbsp;&nbsp;'.$lang_forums['text_posts']."$forumposts<br />".'&nbsp;&nbsp;'.$lang_forums['text_ul']."$uploaded <br />".'&nbsp;&nbsp;'.$lang_forums['text_dl']."$downloaded<br />".'&nbsp;&nbsp;'.$lang_forums['text_ratio']."$ratio";
        echo "<tr><td class=\"rowfollow\" width=\"150\" valign=\"top\" align=\"left\" style='padding: 0px'>".
        UserDisplay::avatarImageWithContext($avatar).'<br /><br /><br />&nbsp;&nbsp;<img alt="'.UserClass::name($arr2['class'], false, false, true).'" title="'.UserClass::name($arr2['class'], false, false, true).'" src="'.$uclass.'" />'.$stats.'</td><td class="rowfollow" valign="top"><br />'.$body."</td></tr>\n";
        $secs = 900;
        $dt = date('Y-m-d H:i:s', TIMENOW - $secs); // calculate date.
        $online = $arr2['last_access'] > $dt;
        echo '<tr><td class="rowfollow" align="center" valign="middle">'.($online ? '<img class="f_online" src="pic/trans.gif" alt="Online" title="'.$lang_forums['title_online'].'" />' : '<img class="f_offline" src="pic/trans.gif" alt="Offline" title="'.$lang_forums['title_offline'].'" />').'<a href="sendmessage.php?receiver='.htmlspecialchars(trim($arr2['id'])).'"><img class="f_pm" src="pic/trans.gif" alt="PM" title="'.$lang_forums['title_send_message_to'].htmlspecialchars($arr2['username'])."\" /></a><a href=\"report.php?forumpost=$postid\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_forums['title_report_this_post'].'" /></a></td>';
        echo '<td class="toolbox" align="right">';

        Hooks::doAction('post_toolbox', ...[$arr, $allPosts, $CURUSER['id']]);

        if ($maypost && $canViewProtected) {
            echo '<a href="'.htmlspecialchars('?action=quotepost&postid='.$postid).'"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="'.$lang_forums['title_reply_with_quote'].'" /></a>';
        }

        if (Permission::can(PermissionEnum::POST_MANAGE) || $is_forummod) {
            echo '<a href="'.htmlspecialchars('?action=deletepost&postid='.$postid).'"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="'.$lang_forums['title_delete_post'].'" /></a>';
        }

        if (($CURUSER['id'] == $posterid && ! $locked) || Permission::can(PermissionEnum::POST_MANAGE) || $is_forummod) {
            echo '<a href="'.htmlspecialchars('?action=editpost&postid='.$postid).'"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="'.$lang_forums['title_edit_post'].'" /></a>';
        }
        echo '</td></tr></table>';
    }

    // ------ Mod options

    if (Permission::can(PermissionEnum::POST_MANAGE) || $is_forummod) {
        echo "</td></tr><tr><td class=\"toolbox\" align=\"center\">\n";
        echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n";
        echo "<tr><td class=\"embedded\"><form method=\"post\" action=\"?action=setsticky\">\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($__server_REQUEST_URI)."\" />\n";
        echo '<input type="hidden" name="sticky" value="'.($sticky ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($sticky ? $lang_forums['submit_unsticky'] : $lang_forums['submit_sticky'])."\" /></form></td>\n";
        echo "<td class=\"embedded\"><form method=\"post\" action=\"?action=setlocked\">\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($__server_REQUEST_URI)."\" />\n";
        echo '<input type="hidden" name="locked" value="'.($locked ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($locked ? $lang_forums['submit_unlock'] : $lang_forums['submit_lock'])."\" /></form></td>\n";
        echo "<td class=\"embedded\"><form method=\"get\" action=\"?\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="forumid" value="'.$forumid."\" />\n";
        echo '<input type="submit" class="medium" value="'.$lang_forums['submit_delete_topic']."\" /></form></td>\n";
        echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=movetopic&topicid='.$topicid)."\">\n".'&nbsp;'.$lang_forums['text_move_thread_to'].'&nbsp;<select class="med" name="forumid">';
        $forums = get_forum_row();
        foreach ($forums as $arr) {
            if ($arr['id'] != $forumid && UserDisplay::currentClass() >= $arr['minclasswrite']) {
                echo '<option value="'.$arr['id'].'">'.htmlspecialchars($arr['name'])."</option>\n";
            }
        }
        echo '</select> <input type="submit" class="medium" value="'.$lang_forums['submit_move'].'" /></form></td>';
        echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=hltopic&topicid='.$topicid)."\">\n".'&nbsp;'.$lang_forums['text_highlight_topic'].'&nbsp;<select class="med" name="color">';
        echo "<option value='0'>".$lang_forums['select_color']."</option>
<option style='background-color: black' value=\"1\">Black</option>
<option style='background-color: sienna' value=\"2\">Sienna</option>
<option style='background-color: darkolivegreen' value=\"3\">Dark Olive Green</option>
<option style='background-color: darkgreen' value=\"4\">Dark Green</option>
<option style='background-color: darkslateblue' value=\"5\">Dark Slate Blue</option>
<option style='background-color: navy' value=\"6\">Navy</option>
<option style='background-color: indigo' value=\"7\">Indigo</option>
<option style='background-color: darkslategray' value=\"8\">Dark Slate Gray</option>
<option style='background-color: darkred' value=\"9\">Dark Red</option>
<option style='background-color: darkorange' value=\"10\">Dark Orange</option>
<option style='background-color: olive' value=\"11\">Olive</option>
<option style='background-color: green' value=\"12\">Green</option>
<option style='background-color: teal' value=\"13\">Teal</option>
<option style='background-color: blue' value=\"14\">Blue</option>
<option style='background-color: slategray' value=\"15\">Slate Gray</option>
<option style='background-color: dimgray' value=\"16\">Dim Gray</option>
<option style='background-color: red' value=\"17\">Red</option>
<option style='background-color: sandybrown' value=\"18\">Sandy Brown</option>
<option style='background-color: yellowgreen' value=\"19\">Yellow Green</option>
<option style='background-color: seagreen' value=\"20\">Sea Green</option>
<option style='background-color: mediumturquoise' value=\"21\">Medium Turquoise</option>
<option style='background-color: royalblue' value=\"22\">Royal Blue</option>
<option style='background-color: purple' value=\"23\">Purple</option>
<option style='background-color: gray' value=\"24\">Gray</option>
<option style='background-color: magenta' value=\"25\">Magenta</option>
<option style='background-color: orange' value=\"26\">Orange</option>
<option style='background-color: yellow' value=\"27\">Yellow</option>
<option style='background-color: lime' value=\"28\">Lime</option>
<option style='background-color: cyan' value=\"29\">Cyan</option>
<option style='background-color: deepskyblue' value=\"30\">Deep Sky Blue</option>
<option style='background-color: darkorchid' value=\"31\">Dark Orchid</option>
<option style='background-color: silver' value=\"32\">Silver</option>
<option style='background-color: pink' value=\"33\">Pink</option>
<option style='background-color: wheat' value=\"34\">Wheat</option>
<option style='background-color: lemonchiffon' value=\"35\">Lemon Chiffon</option>
<option style='background-color: palegreen' value=\"36\">Pale Green</option>
<option style='background-color: paleturquoise' value=\"37\">Pale Turquoise</option>
<option style='background-color: lightblue' value=\"38\">Light Blue</option>
<option style='background-color: plum' value=\"39\">Plum</option>
<option style='background-color: white' value=\"40\">White</option>";
        echo '</select>';
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($__server_REQUEST_URI)."\" />\n";
        echo '<input type="submit" class="medium" value="'.$lang_forums['submit_change'].'" /></form></td>';
        echo "</tr>\n";
        echo "</table>\n";
    }

    Html::endFrame();

    echo $pagerbottom;
    if ($maypost) {
        echo "<br /><table style='border:1px solid #000000;'><tr>".
'<td class="text" align="center"><b>'.$lang_forums['text_quick_reply'].'</b><br /><br />'.
'<form id="compose" name="compose" method="post" action="?action=post" onsubmit="return postvalid(this);">'.
'<input type="hidden" name="id" value="'.$topicid.'" /><input type="hidden" name="type" value="reply" /><br />';
        Html::quickReplyVoid('compose', 'body', $lang_forums['submit_add_reply']);
        echo '</form></td></tr></table>';
        echo '<p align="center"><a class="index" href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'">'.$lang_forums['text_add_reply']."</a></p>\n";
    } elseif ($locked) {
        echo $lang_forums['text_topic_locked_new_denied'];
    } else {
        echo $lang_forums['text_unpermitted_posting_here'];
    }

    echo Html::keyShortcutScript($page, $pages - 1);

    return;
}

// -------- Action: View forum

if ($action == 'viewforum') {
    $forumid = intval(SupportContext::getQuery('forumid') ?? 0);
    LegacyResponse::assertId($forumid, true);
    $userid = intval($CURUSER['id'] ?? 0);
    // ------ Get forum name, moderators
    $row = get_forum_row($forumid);
    if (! $row) {
        Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip']." is trying to visit forum that doesn't exist", 'mod');
        LegacyResponse::abort($lang_forums['std_forum_error'], $lang_forums['std_forum_not_found']);
    }
    if (UserDisplay::currentClass() < $row['minclassread']) {
        LegacyResponse::permissionDenied();
    }

    $forumname = $row['name'];
    $forummoderators = Forum::moderatorsWithContext($forumid, false);
    $search = trim(is_scalar(SupportContext::getQuery('search') ?? '') ? (string) (SupportContext::getQuery('search') ?? '') : '');
    if ($search) {
        $addparam = '&search='.rawurlencode($search);
    } else {
        $addparam = '';
    }

    $sort = (string) (SupportContext::getQuery('sort') ?? 'lastpostdesc');
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
    $num = $topicResult['count'];

    [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($topicsperpage, $num, '?'.'action=viewforum&forumid='.$forumid.$addparam.'&');
    // ------ Get topics data
    $topicResult = ForumRepository::getTopicsByForum((int) $forumid, (string) $search, (string) $sortColumn, (string) $sortDirection, (int) $offset, (int) $perpage);
    $topicRows = $topicResult['rows'];
    $numtopics = $topicRows->count();
    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>--><a class="faqlink" href="'.htmlspecialchars('forums.php?action=viewforum&forumid='.$forumid).'">'.$forumname."</a></h1>\n";
    echo '<br />';
    $maypost = UserDisplay::currentClass() >= $row['minclasswrite'] && UserDisplay::currentClass() >= $row['minclasscreate'] && $CURUSER['forumpost'] == 'yes';

    if (! $maypost) {
        echo '<p><i>'.$lang_forums['text_unpermitted_starting_new_topics']."</i></p>\n";
    }

    echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
    echo '<td class="embedded" width="90%">';
    echo $forummoderators ? '&nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="'.$lang_forums['col_moderator'].'">&nbsp;'.$forummoderators : '';
    echo '</td><td class="embedded nowrap" width="1%">';
    if ($maypost) {
        echo '<a href="'.htmlspecialchars('?action=newtopic&forumid='.$forumid).'"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="'.$lang_forums['title_new_topic'].'" /></a>&nbsp;&nbsp;';
    }
    echo '</td>';
    echo "</tr></table>\n";
    if ($numtopics > 0) {
        echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';

        echo '<tr><td class="colhead" align="center" width="99%">'.$lang_forums['col_topic'].'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.(((SupportContext::getQuery('sort') !== null)) && SupportContext::getQuery('sort') == 'firstpostdesc' ? 'firstpostasc' : 'firstpostdesc')).'" title="'.(((SupportContext::getQuery('sort') !== null)) && SupportContext::getQuery('sort') == 'firstpostdesc' ? $lang_forums['title_order_topic_asc'] : $lang_forums['title_order_topic_desc']).'">'.$lang_forums['col_author'].'</a></td><td class="colhead" align="center">'.$lang_forums['col_replies'].'/'.$lang_forums['col_views'].'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.(((SupportContext::getQuery('sort') !== null)) && SupportContext::getQuery('sort') == 'lastpostasc' ? 'lastpostdesc' : 'lastpostasc')).'" title="'.(((SupportContext::getQuery('sort') !== null)) && SupportContext::getQuery('sort') == 'lastpostasc' ? $lang_forums['title_order_post_desc'] : $lang_forums['title_order_post_asc']).'">'.$lang_forums['col_last_post']."</a></td>\n";

        echo "</tr>\n";
        $counter = 0;

        foreach ($topicRows as $topic) {
            $topicarr = $topic->toArray();
            $topicid = $topicarr['id'];

            $topic_userid = $topicarr['userid'];

            $topic_views = $topicarr['views'];

            $views = number_format($topic_views);

            $locked = $topicarr['locked'] == 'yes';

            $sticky = $topicarr['sticky'] == 'yes';

            $hlcolor = $topicarr['hlcolor'];

            // ---- Get reply count
            if (! $posts = $Cache->get_value('topic_'.$topicid.'_post_count')) {
                $posts = ForumRepository::countTopicPosts((int) $topicid);
                $Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
            }

            $replies = max(0, $posts - 1);

            $tpages = floor($posts / $postsperpage);

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

            // ---- Get userID and date of last post

            $arr = Forum::postRowWithContext($topicarr['lastpost']);
            $lppostid = intval($arr['id'] ?? 0);
            $lpuserid = intval($arr['userid'] ?? 0);
            $lpusername = UserDisplay::username($lpuserid);
            $lpadded = Time::format($arr['added'], true, false);
            $onmouseover = '';
            if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no') {
                if ($CURUSER['timetype'] != 'timealive') {
                    $lastposttime = $lang_forums['text_at_time'].$arr['added'];
                } else {
                    $lastposttime = $lang_forums['text_blank'].Time::format($arr['added'], true, false, true);
                }
                $lptext = Format::formatComment(mb_substr($arr['body'], 0, 100, 'UTF-8').(mb_strlen($arr['body'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                $lastpost_tooltip[$counter]['id'] = 'lastpost_'.$counter;
                $lastpost_tooltip[$counter]['content'] = $lang_forums['text_last_posted_by'].$lpusername.$lastposttime.'<br />'.$lptext;
                $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastpost_tooltip[$counter]['id']."'), 'trail', false,'lifetime', 5000,'styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
            }

            $arr = Forum::postRowWithContext($topicarr['firstpost']);
            $fpuserid = intval($arr['userid'] ?? 0);
            $fpauthor = UserDisplay::username($arr['userid']);

            $subject = ($sticky ? '<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.$lang_forums['title_sticky'].'" />&nbsp;&nbsp;' : '').'<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid).'" '.$onmouseover.'>'.highlight_topic(Format::highlight($search, htmlspecialchars($topicarr['subject'])), $hlcolor).'</a>'.$topicpages;
            $lastpostread = get_last_read_post_id($topicid);

            if ($lastpostread >= $lppostid) {
                $img = get_topic_image($locked ? 'locked' : 'read');
            } else {
                $img = get_topic_image($locked ? 'lockednew' : 'unread');
                if ($lastpostread != $CURUSER['last_catchup']) {
                    $subject .= '&nbsp;&nbsp;<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid.'&page=p'.$lastpostread.'#pid'.$lastpostread).'" title="'.$lang_forums['title_jump_to_unread'].'"><font class="small new"><b>'.$lang_forums['text_new'].'</b></font></a>';
                }
            }

            $topictime = substr($arr['added'], 0, 10);
            if (strtotime($arr['added']) + 86400 > TIMENOW) {
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

        } // while

        // print("</table>\n");
        // print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");
        echo "<tr><td align=\"left\">\n";
        echo '<form method="get" action="forums.php"><b>'.$lang_forums['text_fast_search'].'</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="'.$forumid.'" /><input type="text" style="width: 180px" name="search" />&nbsp;<input type="submit" value="'.$lang_forums['text_go'].'" /></form>';
        echo '</td>';
        ?>
<td align="left" colspan="3">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;"><b><?php echo $lang_forums['text_order']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostdesc"><?php echo $lang_forums['text_topic_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostasc"><?php echo $lang_forums['text_topic_asc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostdesc"><?php echo $lang_forums['text_post_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostasc"><?php echo $lang_forums['text_post_asc']?></a></li>
</ul>
</span>
</span>
</td>
<?php
                echo '</tr></table>';
        echo $pagerbottom;
        if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no') {
            echo Html::tooltipContainer($lastpost_tooltip, 400);
        }
    } // if
    else {
        echo '<p>'.$lang_forums['text_no_topics_found'].'</p>';
    }

    return;
}

// -------- Action: View unread posts

if ($action == 'viewunread') {
    $userid = $CURUSER['id'];

    $beforepostid = intval(SupportContext::getQuery('beforepostid') ?? 0);
    $maxresults = 25;
    $lastCatchup = (int) ($CURUSER['last_catchup'] ?? 0);
    $unreadTopics = ForumRepository::getUnreadTopics($lastCatchup, $beforepostid ?: null, 100);

    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>-->'.$lang_forums['text_topics_with_unread_posts'].'</h1>';

    $n = 0;
    $uc = UserDisplay::currentClass();

    foreach ($unreadTopics as $topic) {
        $arr = $topic->toArray();
        $topiclastpost = $arr['lastpost'];
        $topicid = $arr['id'];

        // ---- Check if post is read
        $lastpostread = get_last_read_post_id($topicid);

        if ($lastpostread >= $topiclastpost) {
            continue;
        }

        $forumid = $arr['forumid'];
        // ---- Check access & get forum name
        $a = get_forum_row($forumid);
        if ($uc < $a['minclassread']) {
            continue;
        }
        $n++;
        if ($n > $maxresults) {
            break;
        }

        $forumname = $a['name'];
        if ($n == 1) {
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
            echo '<tr><td class="colhead" align="left">'.$lang_forums['col_topic'].'</td><td class="colhead" align="left">'.$lang_forums['col_forum']."</td></tr>\n";
        }
        echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".
        get_topic_image('unread').'</td><td class="embedded">'.
        '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.($lastpostread > 0 && $lastpostread != $CURUSER['last_catchup'] ? '&page=p'.$lastpostread.'#pid'.$lastpostread : '')).'">'.highlight_topic(htmlspecialchars($arr['subject']), $arr['hlcolor']).
        '</a></td></tr></table></td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><b>'.$forumname."</b></a></td></tr>\n";
    }
    if ($n > 0) {
        echo "</table>\n";
        echo '<table border="0" class="main" cellspacing="0" cellpadding="5" width="1%"><tr><td class="embedded"><form method="get" action="?"><input type="hidden" name="catchup" value="1" /><input type="submit" value="'.$lang_forums['text_catch_up'].'" class="btn" /></form></td>';
        if ($n > $maxresults) {
            echo '<td class="embedded"><form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="'.$topiclastpost.'" /><input type="submit" value="'.$lang_forums['submit_show_more'].'" class="btn" /></form></td>';
        }
        echo '</tr></table>';
    } else {
        echo '<p>'.$lang_forums['text_nothing_found'].'</p>';
    }

    return;
}

if ($action == 'search') {
    unset($error);
    $error = true;
    $found = '';
    $keywords = htmlspecialchars(trim((string) (SupportContext::getQuery('keywords') ?? '')));
    if ($keywords != '') {
        $searchResult = ForumRepository::searchForumPosts((string) $keywords, (int) UserDisplay::currentClass(), 0, 0);
        $hits = $searchResult['hits'];
        if ($hits) {
            $error = false;
            $found = '[<b><font class="striking"> '.$lang_forums['text_found'].$hits.$lang_forums['text_num_posts'].' </font></b>]';
        }
    }
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
	<div class="search_title"><?php echo $lang_forums['text_search_on_forum'] ?> <?php echo $error && $keywords != '' ? '[<b><font color=striking> '.$lang_forums['text_nothing_found'].'</font></b> ]' : $found?></div>
	<div style="margin-left: 53px; margin-top: 13px;">
		<form method="get" action="forums.php" id="search_form" style="margin: 0pt; padding: 0pt; font-family: Tahoma,Arial,Helvetica,sans-serif; font-size: 11px;">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top"><?php echo $lang_forums['text_by_keyword'] ?></td>
		</tr>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords?>" style="width: 400px;" /></td>
			<td style="padding-bottom: 3px; border: 0;" valign="top"><input name="image" type="image" style="vertical-align: middle; padding-bottom: 0px; margin-left: 0px;" src="<?php echo Forum::picFolderWithContext()?>/search_button.gif" alt="Search" /></td>
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
            echo '<tr><td class="colhead" align="center">'.$lang_forums['col_post'].'</td><td class="colhead" align="center" width="70%">'.$lang_forums['col_topic'].'</td><td class="colhead" align="left">'.$lang_forums['col_forum'].'</td><td class="colhead" align="left">'.$lang_forums['col_posted_by']."</td></tr>\n";

            foreach ($posts as $post) {
                $post = (array) $post;
                echo '<tr><td class="rowfollow" align="center" width="1%">'.$post['id'].'</td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewtopic&topicid='.$post['topicid'].'&highlight='.rawurlencode($keywords).'&page=p'.$post['id'].'#pid'.$post['id']).'">'.highlight_topic(Format::highlight($keywords, htmlspecialchars($post['subject'])), $post['hlcolor']).'</a></td><td class="rowfollow nowrap" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$post['forumid']).'"><b>'.htmlspecialchars($post['forumname']).'</b></a></td><td class="rowfollow nowrap" align="left">'.Time::format($post['added'], true, false).'&nbsp;|&nbsp;'.UserDisplay::username($post['userid'])."</td></tr>\n";
            }

            echo "</table>\n";
            echo $pagerbottom;
        }

    return;
}

if (((SupportContext::getQuery('catchup') !== null)) && SupportContext::getQuery('catchup') == 1) {
    catch_up();
}

// -------- Handle unknown action
if ($action != '') {
    LegacyResponse::abort($lang_forums['std_forum_error'], $lang_forums['std_unknown_action']);
}

// -------- Default action: View forums

// -------- Get forums
if ($CURUSER) {
    ForumRepository::updateUserForumAccess((int) $CURUSER['id'], date('Y-m-d H:i:s'));
}

echo '<h1 align="center">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</h1>';
echo '<p align="center"><a href="?action=search"><b>'.$lang_forums['text_search'].'</b></a> | <a href="?action=viewunread"><b>'.$lang_forums['text_view_unread'].'</b></a> | <a href="?catchup=1"><b>'.$lang_forums['text_catch_up'].'</b></a> '.(Permission::can(PermissionEnum::FORUM_MANAGE) ? '| <a href="forummanage.php"><b>'.$lang_forums['text_forum_manager'].'</b></a>' : '').'</p>';
echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n";

if (! $overforums = $Cache->get_value('overforums_list')) {
    $overforums = ForumRepository::getOverforumsList();
    $Cache->cache_value('overforums_list', $overforums, 86400);
}
foreach ($overforums as $a) {
    if (UserDisplay::currentClass() < $a['minclassview']) {
        continue;
    }
    $forid = $a['id'];
    $overforumname = $a['name'];

    echo '<tr><td align="left" class="colhead" width="99%">'.htmlspecialchars($overforumname).'</td><td align="center" class="colhead">'.$lang_forums['col_topics'].'</td>'.
    '<td align="center" class="colhead">'.$lang_forums['col_posts'].'</td>'.
    '<td align="left" class="colhead">'.$lang_forums['col_last_post'].'</td><td class="colhead" align="left">'.$lang_forums['col_moderator']."</td></tr>\n";

    $forums = get_forum_row();
    foreach ($forums as $forums_arr) {
        if ($forums_arr['forid'] != $forid) {
            continue;
        }
        if (UserDisplay::currentClass() < $forums_arr['minclassread']) {
            continue;
        }

        $forumid = $forums_arr['id'];
        $forumname = htmlspecialchars($forums_arr['name']);
        $forumdescription = htmlspecialchars($forums_arr['description']);

        $forummoderators = Forum::moderatorsWithContext($forums_arr['id'], false);
        if (! $forummoderators) {
            $forummoderators = '<a href="contactstaff.php"><i>'.$lang_forums['text_apply_now'].'</i></a>';
        }

        $topiccount = number_format($forums_arr['topiccount']);
        $postcount = number_format($forums_arr['postcount']);

        // Find last post ID
        // Returns the ID of the last post of a forum
        if (! $arr = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content')) {
            $lastTopic = ForumRepository::getLastTopicByForum((int) $forumid);
            $arr = $lastTopic ? $lastTopic->toArray() : false;
            $Cache->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
        }

        if ($arr) {
            $lastpostid = $arr['lastpost'];
            // Get last post info
            $post_arr = Forum::postRowWithContext($lastpostid);
            $lastposterid = $post_arr['userid'];
            $lastpostdate = Time::format($post_arr['added'], true, false);
            $lasttopicid = $arr['id'];
            $hlcolor = $arr['hlcolor'];
            $lasttopicdissubject = $lasttopicsubject = $arr['subject'];
            $max_length_of_topic_subject = 35;
            $count_dispname = mb_strlen($lasttopicdissubject, 'UTF-8');
            if ($count_dispname > $max_length_of_topic_subject) {
                $lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject - 2, 'UTF-8').'..';
            }
            $lasttopic = highlight_topic(htmlspecialchars($lasttopicdissubject), $hlcolor);

            $lastpost = '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$lasttopicid.'&page=last#last').'" title="'.htmlspecialchars($lasttopicsubject).'">'.$lasttopic.'</a><br />'.$lastpostdate.'&nbsp;|&nbsp;'.UserDisplay::username($lastposterid);

            $lastreadpost = get_last_read_post_id($lasttopicid);

            if ($lastreadpost >= $lastpostid) {
                $img = get_topic_image('read');
            } else {
                $img = get_topic_image('unread');
            }
        } else {
            $lastpost = 'N/A';
            $img = get_topic_image('read');
        }
        $posttodaycount = $Cache->get_value('forum_'.$forumid.'_post_'.$today_date.'_count');
        if ($posttodaycount == '') {
            $posttodaycount = ForumRepository::getForumTodayPostCount((int) $forumid, date('Y-m-d'));
            $Cache->cache_value('forum_'.$forumid.'_post_'.$today_date.'_count', $posttodaycount, 1800);
        }
        if ($posttodaycount > 0) {
            $posttoday = '&nbsp;&nbsp;('.$lang_forums['text_today'].'<b><font class="new">'.$posttodaycount.'</font></b>)';
        } else {
            $posttoday = '';
        }
        echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img.'</td><td class="embedded"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><font class="big"><b>'.$forumname.'</b></font></a>'.$posttoday.
        '<br />'.$forumdescription.'</td></tr></table></td><td class="rowfollow" align="center" width="1%">'.$topiccount.'</td><td class="rowfollow" align="center" width="1%">'.$postcount.'</td>'.
        '<td class="rowfollow nowrap" align="left">'.$lastpost.'</td><td class="rowfollow" align="left">'.$forummoderators."</td></tr>\n";
    }
}
// End Table Mod
echo '</table>';
if ($showforumstats_main == 'yes') {
    forum_stats();
}
?>