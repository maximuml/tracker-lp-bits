<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
//require(get_langfile_path("",true));

$action = htmlspecialchars($_GET["action"]);
$sub = htmlspecialchars($_GET["sub"] ?? '');
$type = htmlspecialchars($_GET["type"]);

loggedinorreturn();
parked();

function check_comment_type($type)
{
	global $lang_comment;
	if($type != "torrent" && $type != "offer")
	stderr($lang_comment['std_error'],$lang_comment['std_error']);
}

check_comment_type($type);

if ($action == "add")
{

	if ($_SERVER["REQUEST_METHOD"] == "POST")
	{
		// Anti Flood Code
		// This code ensures that a member can only send one comment per minute.
		if (!user_can('commanage')) {
			if (strtotime($CURUSER['last_comment']) > (TIMENOW - 10))
			{
				$secs = 10 - (TIMENOW - strtotime($CURUSER['last_comment']));
				stderr($lang_comment['std_error'],$lang_comment['std_comment_flooding_denied']."$secs".$lang_comment['std_before_posting_another']);
			}
		}

		$parent_id = intval($_POST["pid"] ?? 0);
		int_check($parent_id,true);

		$arr = \App\Repositories\CommentRepository::getParent($parent_id, $type);
		if (!$arr)
			stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);

		$text = trim($_POST["body"]);
		if (!$text)
			stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);

		$newid = \App\Repositories\CommentRepository::create($parent_id, $type, $text, (int)$CURUSER["id"]);
		if($type == "torrent"){
			$Cache->delete_value('torrent_'.$parent_id.'_last_comment_content');
		}
		elseif($type == "offer"){
			$Cache->delete_value('offer_'.$parent_id.'_last_comment_content');
		}

		$arg = ['commentpm' => \App\Repositories\CommentRepository::getCommentPmSetting((int)$arr["owner"])];

		if($arg["commentpm"] == 'yes' && $CURUSER['id'] != $arr["owner"])
		{
            $locale = get_user_locale($arr['owner']);
			$subject = nexus_trans("comment.msg_new_comment", [], $locale);
			if($type == "torrent")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale) . " [url=" . get_protocol_prefix() . "$BASEURL/details.php?id=$parent_id] " . $arr['name'] . "[/url].";
			if($type == "offer")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale) . " [url=" . get_protocol_prefix() . "$BASEURL/offers.php?id=$parent_id&off_details=1] " . $arr['name'] . "[/url].";
			if($type == "request")
			$notifs = nexus_trans("comment.msg_torrent_receive_comment", [], $locale). " [url=" . get_protocol_prefix() . "$BASEURL/viewrequests.php?id=$parent_id&req_details=1] " . $arr['name'] . "[/url].";

			\App\Models\Message::add([
				'sender' => 0,
				'receiver' => $arr['owner'],
				'subject' => $subject,
				'added' => now(),
				'msg' => $notifs,
			]);
		}

		KPS("+",$addcomment_bonus,$CURUSER["id"]);

		if($type == "torrent")
			header("Location: details.php?id=$parent_id#$newid");
		else if($type == "offer")
			header("Location: offers.php?id=$parent_id&off_details=1#$newid");
		else if($type == "request")
			header("Location: viewrequests.php?id=$parent_id&req_details=1#$newid");
		die;
	}

	$parent_id = intval($_GET["pid"] ?? 0);
	int_check($parent_id,true);

	if($sub == "quote")
	{
		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		$arr2 = \App\Repositories\CommentRepository::getQuote($commentid);
		if (!$arr2)
			stderr($lang_comment['std_error'], $lang_comment['std_no_comment_id']);
	}

	$arr = \App\Repositories\CommentRepository::getParent($parent_id, $type);
	if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);
	if($type == "torrent"){
		$url="details.php?id=$parent_id";
	}
	else if($type == "offer"){
		$url="offers.php?id=$parent_id&off_details=1";
	}
	else if($type == "request"){
		$url="viewrequests.php?id=$parent_id&req_details=1";
	}

	stdhead($lang_comment['head_add_comment_to']. $arr["name"]);
	begin_main_frame();
	$title = $lang_comment['text_add_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
	print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=add&type=$type\">\n");
	print("<input type=\"hidden\" name=\"pid\" value=\"$parent_id\"/>\n");
	begin_compose($title, ($sub == "quote" ? "quote" : "reply"), ($sub == "quote" ? htmlspecialchars("[quote=".htmlspecialchars($arr2["username"])."]".unesc($arr2["text"])."[/quote]") : ""), false);
	end_compose();
	print("</form>");
	end_main_frame();
	stdfoot();
	die;
}
elseif ($action == "edit")
{
		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		$arr = \App\Repositories\CommentRepository::getForEdit($commentid, $type);
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		if ($arr["user"] != $CURUSER["id"] && !user_can('commanage'))
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		if ($_SERVER["REQUEST_METHOD"] == "POST")
		{
			$text = $_POST["body"];
			$returnto =  htmlspecialchars($_POST["returnto"]) ? $_POST["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

			if ($text == "")
				stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);

			\App\Repositories\CommentRepository::update($commentid, $text, (int)$CURUSER["id"]);
			if($type == "torrent")
				$Cache->delete_value('torrent_'.$arr['parent_id'].'_last_comment_content');
			elseif ($type == "offer")
				$Cache->delete_value('offer_'.$arr['parent_id'].'_last_comment_content');
			header("Location: $returnto");

			die;
		}
		$parent_id = $arr["parent_id"];
		if($type == "torrent")
			$url="details.php?id=$parent_id";
		else if($type == "offer")
			$url="offers.php?id=$parent_id&off_details=1";
		else if($type == "request")
			$url="viewrequests.php?id=$parent_id&req_details=1";
		stdhead($lang_comment['head_edit_comment_to']."\"". $arr["name"] . "\"");
		begin_main_frame();
		$title = $lang_comment['head_edit_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
		print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=edit&cid=$commentid&type=$type\">\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($_SERVER["HTTP_REFERER"]) . "\" />\n");
		begin_compose($title, "edit", htmlspecialchars(unesc($arr["text"])), false);
		end_compose();
		print("</form>");
		end_main_frame();
		stdfoot();
		die;
}
elseif ($action == "delete")
{
		if (!user_can('commanage'))
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = intval($_GET["cid"] ?? 0);
		$sure = $_GET["sure"];
		int_check($commentid,true);

		if (!$sure)
		{
			$referer = $_SERVER["HTTP_REFERER"];
			stderr($lang_comment['std_delete_comment'], $lang_comment['std_delete_comment_note'] ."<a href=comment.php?action=delete&cid=$commentid&sure=1&type=$type" .($referer ? "&returnto=" . rawurlencode($referer) : "") . $lang_comment['std_here_if_sure'],false);
		}
		else
		int_check($sure,true);


		$arr = \App\Repositories\CommentRepository::getForDelete($commentid, $type);
		if (!$arr)
			stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);
		$parent_id = $arr["pid"];
		$userpostid = $arr["user"];

		if (\App\Repositories\CommentRepository::delete($commentid, $type, $parent_id)) {
			if($type == "torrent")
				$Cache->delete_value('torrent_'.$arr['pid'].'_last_comment_content');
			elseif($type == "offer")
				$Cache->delete_value('offer_'.$arr['pid'].'_last_comment_content');
		}

		KPS("-",$addcomment_bonus,$userpostid);

		$returnto = $_GET["returnto"] ? $_GET["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

		header("Location: $returnto");

		die;
}
elseif ($action == "vieworiginal")
{
	if (!user_can('commanage'))
	stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = intval($_GET["cid"] ?? 0);
		int_check($commentid,true);

		$arr = \App\Repositories\CommentRepository::getForViewOriginal($commentid, $type);
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		stdhead($lang_comment['head_original_comment']);
		print("<h1>".$lang_comment['text_original_content_of_comment']."#$commentid</h1>");
		print("<table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">");
		print("<tr><td class=\"text\">\n");
		echo format_comment($arr["ori_text"]);
		print("</td></tr></table>\n");

		$returnto =  htmlspecialchars($_SERVER["HTTP_REFERER"]);

		if ($returnto)
		print("<p><font size=\"small\">(<a href=\"".$returnto."\">".$lang_comment['text_back']."</a>)</font></p>\n");

		stdfoot();

		die;
}
else
stderr($lang_comment['std_error'], $lang_comment['std_unknown_action']);

die;
?>
