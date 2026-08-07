<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
	stderr($lang_takecontact['std_error'], $lang_takecontact['std_method']);

$msg = trim(\App\Support\SupportContext::getPost("body"));
$subject = trim(\App\Support\SupportContext::getPost("subject"));

if (!$msg)
	stderr($lang_takecontact['std_error'],$lang_takecontact['std_please_enter_something']);

if (!$subject)
	stderr($lang_takecontact['std_error'],$lang_takecontact['std_please_define_subject']);

$added = "'" . date("Y-m-d H:i:s") . "'";
$userid = $CURUSER['id'];

// Anti Flood Code
// This code ensures that a member can only send one PM per minute.
if (get_user_class() < UC_MODERATOR) {
	if (strtotime($CURUSER['last_staffmsg']) > (TIMENOW - 60))
	{
		$secs = 60 - (TIMENOW - strtotime($CURUSER['last_staffmsg']));
		stderr($lang_takecontact['std_error'],$lang_takecontact['std_message_flooding'].$secs.$lang_takecontact['std_second'].($secs == 1 ? '' : $lang_takecontact['std_s']).$lang_takecontact['std_before_sending_pm']);
	}
}
\App\Models\StaffMessage::add($userid, $subject, $msg);
// Update Last PM sent...
\App\Models\User::query()->where('id', $CURUSER['id'])->update(['last_staffmsg' => date('Y-m-d H:i:s')]);
$Cache->delete_value('staff_message_count');
$Cache->delete_value('staff_new_message_count');
clear_staff_message_cache();
if (\App\Support\SupportContext::getPost("returnto"))
{
	header("Location: " . htmlspecialchars(\App\Support\SupportContext::getPost("returnto")));
	return;
}

stdhead();
stdmsg($lang_takecontact['std_succeeded'], $lang_takecontact['std_message_succesfully_sent']);
stdfoot();
return;
