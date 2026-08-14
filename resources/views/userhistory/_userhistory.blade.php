<?php

$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
$userid = (int) ($userid ?? 0);
$action = (string) ($action ?? '');
$subject = (string) ($subject ?? \App\Support\UserDisplay::username($userid));
\App\Support\LegacyResponse::assertId($userid, true);

if ($action == "viewposts")
{
    $postcount = (int) ($postcount ?? 0);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');
    $perpage = (int) ($perpage ?? 15);
    $posts = (array) ($posts ?? []);
    $editorNames = (array) ($editorNames ?? []);

    if (empty($posts)) \App\Support\LegacyResponse::abort($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);

    print("<h1>".$lang_userhistory['text_posts_history_for'].$subject."</h1>\n");

    if ($postcount > $perpage) echo $pagertop;

    \App\Support\Html::beginFrame();

    foreach ($posts as $postRow) {
        $arr = $postRow;
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

      $body = \App\Support\Format::formatComment($arr["body"]);

      if (\App\Support\Validators::isId($arr['editedby']))
      {
          $editor = $editorNames[$arr['editedby']] ?? null;
          if ($editor)
          {
              $body .= "<p><font size=1 class=small>".$lang_userhistory['text_last_edited'].\App\Support\UserDisplay::username($arr['editedby']).$lang_userhistory['text_at']."$arr[editdate]</font></p>\n";
          }
      }

      print("<tr valign=top><td class=comment>$body</td></tr>\n");

      print("</td></tr></table>\n");
      print("<br />");
	}

	\App\Support\Html::endFrame();

	if ($postcount > $perpage) echo $pagerbottom;

	return;
}

if ($action == "viewcomments")
{
    $commentcount = (int) ($commentcount ?? 0);
    $pagertop = (string) ($pagertop ?? '');
    $pagerbottom = (string) ($pagerbottom ?? '');
    $perpage = (int) ($perpage ?? 15);
    $comments = (array) ($comments ?? []);
    $commentPageMap = (array) ($commentPageMap ?? []);

    if (empty($comments)) \App\Support\LegacyResponse::abort($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);

    print("<h1>".$lang_userhistory['text_comments_history_for']."$subject</h1>\n");

    if ($commentcount > $perpage) echo $pagertop;

    \App\Support\Html::beginFrame();

    foreach ($comments as $commentRow)
    {
        $arr = $commentRow;

        $commentid = $arr["id"];

        $torrent = $arr["name"];

        if (strlen($torrent) > 55) $torrent = substr($torrent,0,52) . "...";

        $torrentid = $arr["t_id"];

        $count = $commentPageMap[$commentid] ?? 0;
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

        $body = \App\Support\Format::formatComment($arr["text"]);

        print("<tr valign=top><td class=comment>$body</td></tr>\n");

        print("</td></tr></table>\n");

        print("<br />");
    }

    \App\Support\Html::endFrame();

    if ($commentcount > $perpage) echo $pagerbottom;

    return;
}

if ($action != "")
\App\Support\LegacyResponse::abort($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);

\App\Support\Html::stdMessage($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);
