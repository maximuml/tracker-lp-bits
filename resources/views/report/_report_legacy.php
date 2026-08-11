<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$reportofferid = \App\Support\SupportContext::getQuery("reportofferid");
$user = \App\Support\SupportContext::getQuery("user");
$commentid = \App\Support\SupportContext::getQuery("commentid");
$torrent = \App\Support\SupportContext::getQuery("torrent");
$forumpost = \App\Support\SupportContext::getQuery("forumpost");

$takeuser = \App\Support\SupportContext::getPost("takeuser");
$takecommentid = \App\Support\SupportContext::getPost("takecommentid");
$taketorrent = \App\Support\SupportContext::getPost("taketorrent");
$takeforumpost = \App\Support\SupportContext::getPost("takeforumpost");
$takereason = \App\Support\SupportContext::getPost("reason");
$takereportofferid = \App\Support\SupportContext::getPost("takereportofferid");

function takereport($reportid, $type, $reason)
{
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
$lang_report = (array) (\App\Support\SupportContext::getGlobal('lang_report') ?? []);
$Cache = \App\Support\SupportContext::getCache();
	\App\Support\LegacyResponse::assertId($reportid);
	// Check if takereason is set
	if ($reason == ''){
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_missing_reason']);
		die();
	}
	$existing = \Nexus\Database\NexusDB::table('reports')
	    ->where('addedby', $CURUSER['id'])
	    ->where('reportid', $reportid)
	    ->where('type', $type)
	    ->exists();
	if (!$existing)
	{
		\Nexus\Database\NexusDB::table('reports')->insert([
		    'addedby' => $CURUSER['id'],
		    'reportid' => $reportid,
		    'type' => $type,
		    'reason' => trim($reason),
		    'added' => date("Y-m-d H:i:s"),
		]);
		$Cache->delete_value('staff_report_count');
		$Cache->delete_value('staff_new_report_count');
		\App\Support\LegacyResponse::abort($lang_report['std_message'], $lang_report['std_successfully_reported']);
		die();
	}
	else
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_already_reported_this']);
		die();
	}
}

//////////OFFER #1 START//////////
if ((isset($takereportofferid)) && (isset($takereason)))
{
	takereport($takereportofferid, 'offer', $takereason);
}
//////////OFFER #1 END//////////

//////////USER #1 START//////////
elseif (((isset($takeuser))) && ((isset($takereason))))
{
	takereport($takeuser, 'user', $takereason);
}
//////////USER #1 END//////////

//////////TORRENT #1 START//////////
elseif (((isset($taketorrent))) && ((isset($takereason))))
{
	takereport($taketorrent, 'torrent', $takereason);
}
//////////TORRENT #1 END//////////

//////////FORUM POST #1 START//////////
elseif (((isset($takeforumpost))) && ((isset($takereason))))
{
	takereport($takeforumpost, 'post', $takereason);
}
//////////FORUM #1 END//////////

//////////COMMENT #1 START//////////
elseif (((isset($takecommentid))) && ((isset($takereason))))
{
	takereport($takecommentid, 'comment', $takereason);
}
//////////COMMENT #1 END//////////

//////////USER #2 START//////////
elseif ((isset($user)))
{
	\App\Support\LegacyResponse::assertId($user);
	if ($user == $CURUSER['id']) {
		\App\Support\LegacyResponse::abort($lang_report['std_sorry'], $lang_report['std_cannot_report_oneself']);
		die;
	}
	$userRow = \App\Models\User::query()->where('id', $user)->first(['username', 'class']);
	if (!$userRow)
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_user_id']);
		die();
	}

	$arr = $userRow->toArray();
	if ($arr["class"] >= $staffmem_class)
	{
		\App\Support\LegacyResponse::abort($lang_report['std_sorry'], $lang_report['std_cannot_report'].\App\Support\UserClass::name($arr["class"],false,true,true), false);
		die();
	}

	else
	{
		\App\Support\LegacyResponse::abort($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_user'].\App\Support\UserDisplay::username(htmlspecialchars($user)).$lang_report['text_to_staff']."<br />".$lang_report['text_not_for_leechers']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takeuser value=\"".htmlspecialchars($user)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
	}
}
//////////USER #2 END//////////

//////////TORRENT #2 START//////////
elseif ((isset($torrent)))
{
	\App\Support\LegacyResponse::assertId($torrent);
	$name = \App\Models\Torrent::query()->where('id', $torrent)->value('name');

	if (!$name)
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_torrent_id']);
		die();
	}
	$arr = ['name' => $name];
	\App\Support\LegacyResponse::abort($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_torrent']."<a href=details.php?id=".htmlspecialchars($torrent)."><b>".htmlspecialchars($arr['name'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=taketorrent value=\"".htmlspecialchars($torrent)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////TORRENT #2 END//////////

//////////FORUM POST #2 START//////////
elseif ((isset($forumpost)))
{
	\App\Support\LegacyResponse::assertId($forumpost);
	$arr = (array) \Nexus\Database\NexusDB::table('topics')
	    ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
	    ->where('posts.id', $forumpost)
	    ->first(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid']);

	if (empty($arr))
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_post_id']);
	}
	\App\Support\LegacyResponse::abort($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_post'].$forumpost.$lang_report['text_of_topic']."<a href=\"forums.php?action=viewtopic&topicid=".$arr['topicid']."&page=p".htmlspecialchars($forumpost)."#".htmlspecialchars($forumpost)."\"><b>".htmlspecialchars($arr['subject'])."</b></a>".$lang_report['text_by'].\App\Support\UserDisplay::username($arr['postuserid']).$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takeforumpost value=\"".htmlspecialchars($forumpost)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////FORUM POST #2 END//////////

//////////COMMENT #2 START//////////
elseif ((isset($commentid)))
{
	\App\Support\LegacyResponse::assertId($commentid);
	$comment = \App\Models\Comment::query()->where('id', $commentid)->first(['id', 'user', 'torrent', 'request', 'offer']);
	if (!$comment)
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_comment_id']);
	}
	$arr = $comment->toArray();
	if ($arr['torrent']){ //Comment of torrent. BTW, this is shitty code!
		$name = \App\Models\Torrent::query()->where('id', $arr['torrent'])->value('name');
		$url = "details.php?id=".$arr['torrent']."#".$commentid;
		$of = $lang_report['text_of_torrent'];
	}
	elseif ($arr['offer']){ //Comment of offer
		$name = \App\Models\Offer::query()->where('id', $arr['offer'])->value('name');
		$url = "offers.php?id=".$arr['offer']."&off_details=1#".$commentid;
		$of = $lang_report['text_of_offer'];
	}
	else //Comment belongs to no one
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_orphaned_comment']);

	\App\Support\LegacyResponse::abort($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_comment'].$commentid.$of."<a href=\"".$url."\"><b>".htmlspecialchars($name)."</b></a>".$lang_report['text_by'].\App\Support\UserDisplay::username($arr['user']).$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takecommentid value=\"".htmlspecialchars($commentid)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////COMMENT #2 END//////////

//////////OFFER #2 START//////////
elseif ((isset($reportofferid)))
{
	\App\Support\LegacyResponse::assertId($reportofferid);
	$offer = \App\Models\Offer::query()->where('id', $reportofferid)->first(['id', 'name']);
	if (!$offer)
	{
		\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_offer_id']);
	}
	$arr = $offer->toArray();
	\App\Support\LegacyResponse::abort($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_offer']."<a href=\"offers.php?id=".$arr['id']."&off_details=1\"><b>".htmlspecialchars($arr['name'])."</b></a>".$lang_report['text_to_staff']."<br />".$lang_report['text_reason_note']."<br /><form method=post action=report.php><input type=hidden name=takereportofferid value=\"".htmlspecialchars($reportofferid)."\">".$lang_report['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_report['submit_confirm']."\"></form>", false);
}
//////////OFFERT #2 END//////////

else // unknown action
	\App\Support\LegacyResponse::abort($lang_report['std_error'], $lang_report['std_invalid_action']);
?>
