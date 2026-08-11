<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
$userid = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
int_check($userid,true);

if ($CURUSER["id"] != $userid && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY))
permissiondenied();

$action = htmlspecialchars(\App\Support\SupportContext::getQuery("action"));

$perpage = 15;

if ($action == "viewposts")
{
	$postcount = \Nexus\Database\NexusDB::table('posts as p')
	    ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
	    ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
	    ->where('p.userid', $userid)
	    ->where('f.minclassread', '<=', $CURUSER['class'])
	    ->distinct()
	    ->count('p.id');

	list($pagertop, $pagerbottom, , $offset, $perpage) = pager($perpage, $postcount, $__server_PHP_SELF . "?action=viewposts&id=$userid&");

	$subject = get_username($userid);

	$posts = \Nexus\Database\NexusDB::table('posts as p')
	    ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
	    ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
	    ->leftJoin('readposts as r', function ($join) use ($userid) {
	        $join->on('p.topicid', '=', 'r.topicid')->on('p.userid', '=', 'r.userid');
	    })
	    ->where('p.userid', $userid)
	    ->where('f.minclassread', '<=', $CURUSER['class'])
	    ->orderByDesc('p.id')
	    ->offset($offset)
	    ->limit($perpage)
	    ->get(['f.id AS f_id', 'f.name', 't.id AS t_id', 't.subject', 't.lastpost', 'r.lastpostread', 'p.*']);

	if ($posts->isEmpty()) stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);

	stdhead($lang_userhistory['head_posts_history']);

	print("<h1>".$lang_userhistory['text_posts_history_for'].$subject."</h1>\n");

	if ($postcount > $perpage) echo $pagertop;

	begin_main_frame();

	begin_frame();

	foreach ($posts as $postRow) {
		$arr = (array) $postRow;
		$postid = $arr["id"];

		$posterid = $arr["userid"];

		$topicid = $arr["t_id"];

		$topicname = $arr["subject"];

		$forumid = $arr["f_id"];

		$forumname = $arr["name"];

		$newposts = ($arr["lastpostread"] < $arr["lastpost"]) && $CURUSER["id"] == $userid;

		$added = \App\Support\Time::format($arr["added"], true, false, false);

		print("<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
	    $added&nbsp;--&nbsp;".$lang_userhistory['text_forum'].
	    "<a href=forums.php?action=viewforum&forumid=$forumid>$forumname</a>
	    &nbsp;--&nbsp;".$lang_userhistory['text_topic'].
	    "<a href=forums.php?action=viewtopic&topicid=$topicid>$topicname</a>
      &nbsp;--&nbsp;".$lang_userhistory['text_post'].
      "<a href=forums.php?action=viewtopic&topicid=$topicid&page=p$postid#pid$postid>#$postid</a>" .
      ($newposts ? " &nbsp;<b>(<font class=new>".$lang_userhistory['text_new']."</font>)</b>" : "") .
      "</td></tr></table></p>\n");

      print("<br />");

      print("<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n");

      $body = format_comment($arr["body"]);

      if (is_valid_id($arr['editedby']))
      {
      	$editor = \App\Models\User::query()->where('id', $arr['editedby'])->value('username');
      	if ($editor)
      	{
      		$body .= "<p><font size=1 class=small>".$lang_userhistory['text_last_edited'].get_username($arr['editedby']).$lang_userhistory['text_at']."$arr[editdate]</font></p>\n";
      	}
      }

      print("<tr valign=top><td class=comment>$body</td></tr>\n");

      print("</td></tr></table>\n");
      print("<br />");
	}

	end_frame();

	end_main_frame();

	if ($postcount > $perpage) echo $pagerbottom;

	stdfoot();

	die;
}

if ($action == "viewcomments")
{
	$commentcount = \Nexus\Database\NexusDB::table('comments as c')
	    ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
	    ->where('c.user', $userid)
	    ->count();

	list($pagertop, $pagerbottom, , $offset, $perpage) = pager($perpage, $commentcount, $__server_PHP_SELF . "?action=viewcomments&id=$userid&");

	$subject = get_username($userid);

	$comments = \Nexus\Database\NexusDB::table('comments as c')
	    ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
	    ->where('c.user', $userid)
	    ->orderByDesc('c.id')
	    ->offset($offset)
	    ->limit($perpage)
	    ->get(['t.name', 'c.torrent AS t_id', 'c.id', 'c.added', 'c.text']);

	if ($comments->isEmpty()) stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);

	stdhead($lang_userhistory['head_comments_history']);

	print("<h1>".$lang_userhistory['text_comments_history_for']."$subject</h1>\n");

	if ($commentcount > $perpage) echo $pagertop;

	begin_main_frame();

	begin_frame();

	foreach ($comments as $commentRow)
	{
		$arr = (array) $commentRow;

		$commentid = $arr["id"];

		$torrent = $arr["name"];

		if (strlen($torrent) > 55) $torrent = substr($torrent,0,52) . "...";

		$torrentid = $arr["t_id"];

		$count = \Nexus\Database\NexusDB::table('comments')->where('torrent', $torrentid)->where('id', '<', $commentid)->count();
		$comm_page = floor($count/20);
		$page_url = $comm_page?"&page=$comm_page":"";

		$added = \App\Support\Time::format($arr["added"], true, false, false);

		print("<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>".
		"$added&nbsp;---&nbsp;".$lang_userhistory['text_torrent'].
		($torrent?("<a href=details.php?id=$torrentid&tocomm=1&hit=1>$torrent</a>"):" [Deleted] ").
		"&nbsp;---&nbsp;".$lang_userhistory['text_comment']."</b>#<a href=details.php?id=$torrentid&tocomm=1&hit=1$page_url>$commentid</a>
	  </td></tr></table></p>\n");
		print("<br />");

		print("<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n");

		$body = format_comment($arr["text"]);

		print("<tr valign=top><td class=comment>$body</td></tr>\n");

		print("</td></tr></table>\n");

		print("<br />");
	}

	end_frame();

	end_main_frame();

	if ($commentcount > $perpage) echo $pagerbottom;

	stdfoot();

	die;
}

if ($action != "")
stderr($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);

stdhead($lang_userhistory['head_user_history']);
stdmsg($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);
stdfoot();
?>
