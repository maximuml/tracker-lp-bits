<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_news)) $lang_news = (array) (\App\Support\SupportContext::getGlobal('lang_news') ?? []);

$mode = (string) ($mode ?? 'add');
$title = (string) ($title ?? ($lang_news['text_submit_news_item'] ?? 'Submit news item'));
$newsid = (int) ($newsid ?? 0);
$body = (string) ($body ?? '');
$subject = (string) ($subject ?? '');
$notify = (string) ($notify ?? 'no');
$returnto = (string) ($returnto ?? '');

$checked = $notify === 'yes' ? ' checked="checked"' : '';

if ($mode === 'edit') {
    $actionUrl = htmlspecialchars("?action=edit&newsid=" . $newsid);
} else {
    $actionUrl = "?action=add";
}

print("<form id=\"compose\" name=\"compose\" method=\"post\" action=\"" . $actionUrl . "\">\n");
\App\Support\Frame::composeBeginVoid($title, $mode === 'edit' ? 'edit' : 'new', $body, true, $subject);
print("<tr><td class=\"toolbox\" align=\"center\" colspan=\"2\"><input type=\"checkbox\" name=\"notify\" value=\"yes\"" . $checked . " />" . ($lang_news['text_notify_users_of_this'] ?? 'Notify users of this') . "</td></tr>\n");
\App\Support\Frame::composeEndVoid();
if ($mode === 'edit') {
    print("<input type=\"hidden\" name=\"returnto\" value=\"" . $returnto . "\" />");
}
print("</form>");
