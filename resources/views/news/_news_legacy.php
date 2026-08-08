<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_HTTP_REFERER = \App\Support\SupportContext::getServerValue('HTTP_REFERER');
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
$action = htmlspecialchars(\App\Support\SupportContext::getQuery("action") ?? '');

//  Delete News Item    //////////////////////////////////////////////////////

if ($action == 'delete')
{
	$newsid = intval(\App\Support\SupportContext::getQuery("newsid") ?? 0);
	int_check($newsid,true);

	$returnto = !empty(\App\Support\SupportContext::getQuery("returnto")) ? htmlspecialchars(\App\Support\SupportContext::getQuery("returnto")) : htmlspecialchars($__server_HTTP_REFERER);

	$sure = intval(\App\Support\SupportContext::getQuery("sure") ?? 0);
	if (!$sure)
	stderr($lang_news['std_delete_news_item'], $lang_news['std_are_you_sure'] . "<a class=altlink href=?action=delete&newsid=$newsid&returnto=$returnto&sure=1>".$lang_news['std_here']."</a>".$lang_news['std_if_sure'],false);

	\App\Models\News::query()->where('id', $newsid)->delete();
	$Cache->delete_value('recent_news','true');
	if ($returnto != "")
	header("Location: $returnto");
	else
	header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
}

//  Add News Item    /////////////////////////////////////////////////////////

if ($action == 'add')
{
	$body = htmlspecialchars(\App\Support\SupportContext::getPost('body'),ENT_QUOTES);
	if (!$body)
	stderr($lang_news['std_error'], $lang_news['std_news_body_empty']);

	$title = htmlspecialchars(\App\Support\SupportContext::getPost('subject'));
	if (!$title)
	stderr($lang_news['std_error'], $lang_news['std_news_title_empty']);

	$added = intval(\App\Support\SupportContext::getPost("added") ?? 0);
	if (!$added)
	$added = date("Y-m-d H:i:s");
	$notify = \App\Support\SupportContext::getPost('notify') ?? '';
	if ($notify != 'yes')
		$notify = 'no';
	$newsId = \App\Models\News::query()->insertGetId([
	    'userid' => $CURUSER['id'],
	    'added' => $added,
	    'body' => $body,
	    'title' => $title,
	    'notify' => $notify,
	]);
	$Cache->delete_value('recent_news',true);
	if (!$newsId) {
        stderr($lang_news['std_error'], $lang_news['std_something_weird_happened']);
    }
	fire_event("news_created", \App\Models\News::query()->find($newsId));
	header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
}

//  Edit News Item    ////////////////////////////////////////////////////////

if ($action == 'edit')
{

	$newsid = intval(\App\Support\SupportContext::getQuery("newsid") ?? 0);
	int_check($newsid,true);

	$news = \App\Models\News::query()->where('id', $newsid)->first();

	if (!$news)
	stderr($lang_news['std_error'], $lang_news['std_invalid_news_id'].$newsid);

	$arr = $news->toArray();

	if ($__server_REQUEST_METHOD == 'POST')
	{
		$body = htmlspecialchars(\App\Support\SupportContext::getPost('body'),ENT_QUOTES);

		if ($body == "")
		stderr($lang_news['std_error'], $lang_news['std_news_body_empty']);

		$title = htmlspecialchars(\App\Support\SupportContext::getPost('subject'));

		if ($title == "")
		stderr($lang_news['std_error'], $lang_news['std_news_title_empty']);

		$notify = \App\Support\SupportContext::getPost('notify') ?? '';
		if ($notify != 'yes')
			$notify = 'no';
		\App\Models\News::query()->where('id', $newsid)->update([
		    'body' => $body,
		    'title' => $title,
		    'notify' => $notify,
		]);
		$Cache->delete_value('recent_news',true);
		header("Location: " . get_protocol_prefix() . "$BASEURL/index.php");
	}
	else
	{
		stdhead($lang_news['head_edit_site_news']);
		begin_main_frame();
		$body = $arr["body"];
		$subject = htmlspecialchars($arr['title']);
		$title = $lang_news['text_edit_site_news'];
		print("<form id=\"compose\" name=\"compose\" method=\"post\" action=\"".htmlspecialchars("?action=edit&newsid=".$newsid)."\">");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".($returnto ?? '')."\" />");
		begin_compose($title, "edit", $body, true, $subject);
		print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" ".($arr['notify'] == 'yes' ? " checked=\"checked\"" : "")." />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
		end_compose();
		end_main_frame();
		stdfoot();
		die;
	}

}

//  Other Actions and followup    ////////////////////////////////////////////

stdhead($lang_news['head_site_news']);
begin_main_frame();
$title = $lang_news['text_submit_news_item'];
print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=add\">\n");
begin_compose($title, 'new');
print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
end_compose();
print("</form>");
end_main_frame();
stdfoot();
die;

?>
