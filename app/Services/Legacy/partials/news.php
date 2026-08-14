<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_news)) $lang_news = (array) (\App\Support\SupportContext::getGlobal('lang_news') ?? []);
$__server_HTTP_REFERER = \App\Support\SupportContext::getServerValue('HTTP_REFERER');
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
$action = htmlspecialchars(\App\Support\SupportContext::getQuery("action") ?? '');

//  Delete News Item    //////////////////////////////////////////////////////

if ($action == 'delete')
{
	$newsid = intval(\App\Support\SupportContext::getQuery("newsid") ?? 0);
	\App\Support\LegacyResponse::assertId($newsid, true);

	$returnto = !empty(\App\Support\SupportContext::getQuery("returnto")) ? htmlspecialchars(\App\Support\SupportContext::getQuery("returnto")) : htmlspecialchars($__server_HTTP_REFERER);

	$sure = intval(\App\Support\SupportContext::getQuery("sure") ?? 0);
	if (!$sure)
	\App\Support\LegacyResponse::abort($lang_news['std_delete_news_item'], $lang_news['std_are_you_sure'] . "<a class=altlink href=?action=delete&newsid=$newsid&returnto=$returnto&sure=1>".$lang_news['std_here']."</a>".$lang_news['std_if_sure'], false);

	\App\Models\News::query()->where('id', $newsid)->delete();
	$Cache->delete_value('recent_news','true');
	if ($returnto != "")
	header("Location: $returnto");
	else
	header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/index.php");
}

//  Add News Item    /////////////////////////////////////////////////////////

if ($action == 'add')
{
	$body = htmlspecialchars(\App\Support\SupportContext::getPost('body'),ENT_QUOTES);
	if (!$body)
	\App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_news_body_empty']);

	$title = htmlspecialchars(\App\Support\SupportContext::getPost('subject'));
	if (!$title)
	\App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_news_title_empty']);

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
        \App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_something_weird_happened']);
    }
	\App\Support\Events::fire("news_created", \App\Models\News::query()->find($newsId), null);
	header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/index.php");
}

//  Edit News Item    ////////////////////////////////////////////////////////

if ($action == 'edit')
{

	$newsid = intval(\App\Support\SupportContext::getQuery("newsid") ?? 0);
	\App\Support\LegacyResponse::assertId($newsid, true);

	$news = \App\Models\News::query()->where('id', $newsid)->first();

	if (!$news)
	\App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_invalid_news_id'].$newsid);

	$arr = $news->toArray();

	if ($__server_REQUEST_METHOD == 'POST')
	{
		$body = htmlspecialchars(\App\Support\SupportContext::getPost('body'),ENT_QUOTES);

		if ($body == "")
		\App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_news_body_empty']);

		$title = htmlspecialchars(\App\Support\SupportContext::getPost('subject'));

		if ($title == "")
		\App\Support\LegacyResponse::abort($lang_news['std_error'], $lang_news['std_news_title_empty']);

		$notify = \App\Support\SupportContext::getPost('notify') ?? '';
		if ($notify != 'yes')
			$notify = 'no';
		\App\Models\News::query()->where('id', $newsid)->update([
		    'body' => $body,
		    'title' => $title,
		    'notify' => $notify,
		]);
		$Cache->delete_value('recent_news',true);
		header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/index.php");
	}
	else
	{
			$body = $arr["body"];
		$subject = htmlspecialchars($arr['title']);
		$title = $lang_news['text_edit_site_news'];
		print("<form id=\"compose\" name=\"compose\" method=\"post\" action=\"".htmlspecialchars("?action=edit&newsid=".$newsid)."\">");
		print("<input type=\"hidden\" name=\"returnto\" value=\"".($returnto ?? '')."\" />");
		\App\Support\Frame::composeBeginVoid($title, "edit", $body, true, $subject);
		print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" ".($arr['notify'] == 'yes' ? " checked=\"checked\"" : "")." />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
		\App\Support\Frame::composeEndVoid();
		return;
	}

}

//  Other Actions and followup    ////////////////////////////////////////////

$title = $lang_news['text_submit_news_item'];
print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=add\">\n");
\App\Support\Frame::composeBeginVoid($title, 'new');
print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\" />".$lang_news['text_notify_users_of_this']."</td></tr>\n");
\App\Support\Frame::composeEndVoid();
print("</form>");
return;

?>