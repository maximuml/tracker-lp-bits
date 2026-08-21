<?php

use App\Support\Frame;
use App\Support\SupportContext;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($lang_news)) {
    $lang_news = (array) (SupportContext::getGlobal('lang_news') ?? []);
}

$mode = (string) ($mode ?? 'add');
$title = (string) ($title ?? ($lang_news['text_submit_news_item'] ?? 'Submit news item'));
$newsid = (int) ($newsid ?? 0);
$body = (string) ($body ?? '');
$subject = (string) ($subject ?? '');
$notify = (string) ($notify ?? 'no');
$returnto = (string) ($returnto ?? '');

$checked = $notify === 'yes' ? ' checked="checked"' : '';

if ($mode === 'edit') {
    $actionUrl = htmlspecialchars('?action=edit&newsid='.$newsid);
} else {
    $actionUrl = '?action=add';
}

echo '<form id="compose" name="compose" method="post" action="'.$actionUrl."\">\n";
Frame::composeBeginVoid($title, $mode === 'edit' ? 'edit' : 'new', $body, true, $subject);
echo '<tr><td class="toolbox" align="center" colspan="2"><input type="checkbox" name="notify" value="yes"'.$checked.' />'.($lang_news['text_notify_users_of_this'] ?? 'Notify users of this')."</td></tr>\n";
Frame::composeEndVoid();
if ($mode === 'edit') {
    echo '<input type="hidden" name="returnto" value="'.$returnto.'" />';
}
echo '</form>';
