<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$type = \App\Support\SupportContext::getRequestInput('type');
if ($type === null)
	return;
if ($type == "adminactivate")
{
	\App\Support\Html::stdhead($lang_ok['head_user_signup']);
	\App\Support\Html::stdMessage($lang_ok['std_account_activated'], $lang_ok['account_activated_note']);
}
elseif ($type == "inviter")
{
	\App\Support\Html::stdhead($lang_ok['head_user_signup']);
	\App\Support\Html::stdMessage($lang_ok['std_account_activated'], $lang_ok['account_activated_note_two']);
}
elseif ($type == "signup")
{
	$email = \App\Support\SupportContext::getRequestInput('email');
	if ($email === null)
		return;
	\App\Support\Html::stdhead($lang_ok['head_user_signup']);
        \App\Support\Html::stdMessage($lang_ok['std_signup_successful'], $lang_ok['std_confirmation_email_note']. htmlspecialchars($email) . $lang_ok['std_confirmation_email_note_end']);
	\App\Support\Html::stdfoot();
}
elseif ($type == "sysop") {
		\App\Support\Html::stdhead($lang_ok['head_sysop_activation']);
		print($lang_ok['std_sysop_activation_note']);
	if (isset($CURUSER))
		print($lang_ok['std_auto_logged_in_note']);
	else
		print($lang_ok['std_cookies_disabled_note']);
	\App\Support\Html::stdfoot();
	}
elseif ($type == "confirmed") {
	\App\Support\Html::stdhead($lang_ok['head_already_confirmed']);
	print($lang_ok['std_already_confirmed']);
	print($lang_ok['std_already_confirmed_note']);
	\App\Support\Html::stdfoot();
}
elseif ($type == "confirm") {
	if (isset($CURUSER)) {
		\App\Support\Html::stdhead($lang_ok['head_signup_confirmation']);
		print($lang_ok['std_account_confirmed']);
		print($lang_ok['std_auto_logged_in_note']);
		echo sprintf($lang_ok['std_read_rules_faq'], \App\Models\Setting::getSiteName());
		\App\Support\Html::stdfoot();
	}
	else {
		\App\Support\Html::stdhead($lang_ok['head_signup_confirmation']);
		print($lang_ok['std_account_confirmed']);
		print($lang_ok['std_cookies_disabled_note']);
		\App\Support\Html::stdfoot();
	}
}
else
	return;
