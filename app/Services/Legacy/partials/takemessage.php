<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_takemessage)) $lang_takemessage = (array) (\App\Support\SupportContext::getGlobal('lang_takemessage') ?? []);
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
	\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_permission_denied']);

	$origmsg = intval(\App\Support\SupportContext::getPost("origmsg") ?? 0);
	$msg = trim(\App\Support\SupportContext::getPost("body"));
	if (((\App\Support\SupportContext::getPost('forward') !== null)) && \App\Support\SupportContext::getPost('forward') == 1) //this is forwarding
	{
		if (!$origmsg)
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_invalid_id']);
		$origmsgRecord = \App\Models\Message::query()->where('id', $origmsg)
		    ->where(function ($query) {
		        $query->where('receiver', \App\Support\SupportContext::getGlobal('CURUSER')['id'])
		              ->orWhere('sender', \App\Support\SupportContext::getGlobal('CURUSER')['id']);
		    })->first();
		if (!$origmsgRecord)
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_no_permission_forwarding']);
		$origmsgrow = $origmsgRecord->toArray();
		if(!\App\Support\SupportContext::getPost('to'))
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_must_enter_username']);
		$receiver = \App\Support\UserDisplay::userIdFromName(trim(\App\Support\SupportContext::getPost('to')));
        $locale = \App\Support\Locale::userLocale($receiver);
		if ($origmsgrow['sender'] == 0)
		{
			$origfrom = \App\Support\Locale::trans("message.msg_system", [], $locale);
		}
		else
		{
			$origmsgsendername = \App\Support\UserDisplay::plainUsername($origmsgrow['sender']);
			$origfrom = "[url=userdetails.php?id=".$origmsgrow['sender']."]".$origmsgsendername."[/url]";
		}
		$msg = "-------- ".\App\Support\Locale::trans("message.msg_original_message_from", [], $locale) . $origfrom . " --------\n" . $origmsgrow['msg']."\n\n".($msg ? "-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n".$msg : "");

	}
	else
	{
		$receiver = intval(\App\Support\SupportContext::getPost("receiver") ?? 0);
		if (!\App\Support\Validators::isId($receiver) || ($origmsg && !\App\Support\Validators::isId($origmsg)))
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_invalid_id']);
		$bodyadd = "";
		if (!$msg)
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_please_enter_something']);
	}
	$save = \App\Support\SupportContext::getPost("save");
	$returnto = \App\Support\SupportContext::getPost("returnto");

	// Anti Flood Code
	// This code ensures that a member can only send one PM every 10 seconds.
	if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER)) {
		if (strtotime($CURUSER['last_pm']) > (TIMENOW - 10))
		{
			$secs = 60 - (TIMENOW - strtotime($CURUSER['last_pm']));
			\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_message_flooding_denied'].$secs.$lang_takemessage['std_before_sending_pm']);
		}
	}

	// Change
	$save = ($save == 'yes') ? "yes" : "no";
	// End of Change

	$user = \Nexus\Database\NexusDB::table('users')
	    ->where('id', $receiver)
	    ->select('id', 'username', 'parked', 'email', 'acceptpms', 'notifs', \Nexus\Database\NexusDB::raw(\Nexus\Database\NexusDB::unixTimestampField('last_access') . ' as la'))
	    ->first();
	if (!$user)
		\App\Support\LegacyResponse::abort($lang_takemessage['std_error'], $lang_takemessage['std_user_not_exist']);
	$user = (array) $user;

	//Make sure recipient wants this message
	if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER))
	{
		if ($user["parked"] == "yes")
		\App\Support\LegacyResponse::abort($lang_takemessage['std_refused'], $lang_takemessage['std_account_parked']);
		if ($user["acceptpms"] == "yes")
		{
			$blocked = \Nexus\Database\NexusDB::table('blocks')
			    ->where('userid', $receiver)
			    ->where('blockid', $CURUSER["id"])
			    ->count() > 0;
			if ($blocked)
			\App\Support\LegacyResponse::abort($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_your_pms']);
		}
		elseif ($user["acceptpms"] == "friends")
		{
			$isFriend = \Nexus\Database\NexusDB::table('friends')
			    ->where('userid', $receiver)
			    ->where('friendid', $CURUSER["id"])
			    ->count() > 0;
			if (!$isFriend)
			\App\Support\LegacyResponse::abort($lang_takemessage['std_refused'], $lang_takemessage['std_user_accepts_friends_pms']);
		}
		elseif ($user["acceptpms"] == "no")
		\App\Support\LegacyResponse::abort($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_all_pms']);
	}

	$subject = trim(\App\Support\SupportContext::getPost('subject'));

	$messageRecord = \App\Models\Message::add([
		'sender' => $CURUSER["id"],
		'receiver' => $receiver,
		'msg' => $msg,
		'subject' => $subject,
		'added' => now(),
		'saved' => $save,
		'location' => 1,
	]);

	$Cache->delete_value('user_'.$CURUSER["id"].'_outbox_count');

	$msgid=$messageRecord->id;
	$date=date("Y-m-d H:i:s");
	// Update Last PM sent...
	\App\Models\User::query()->where('id', $CURUSER['id'])->update(['last_pm' => date("Y-m-d H:i:s")]);
	$Cache->delete_value('user_'.$CURUSER['id'].'_content');

	// Send notification email.
if ($emailnotify_smtp=='yes' && $smtptype != 'none'){
	$mystring = $user['notifs'];
	$findme  = '[pm]';
	$pos = strpos($mystring, $findme);
	if ($pos === false)
	$sm = false;
	else
	$sm = true;

	if ($sm)
	{

		$username = trim($CURUSER["username"]);
		$msg_receiver = trim($user["username"]);
		$prefix = \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure());
        $locale = \App\Support\Locale::userLocale($user['id']);
		$title = "$SITENAME ".\App\Support\Locale::trans("message.mail_received_pm_from", [], $locale) . $username . "!";
        $mailDear = \App\Support\Locale::trans("message.mail_dear", [], $locale);
        $mailYouReceivedAPm = \App\Support\Locale::trans("message.mail_you_received_a_pm", [], $locale);
        $mailSender = \App\Support\Locale::trans("message.mail_sender", [], $locale);
        $mailSubject = \App\Support\Locale::trans("message.mail_subject", [], $locale);
        $mailDate = \App\Support\Locale::trans("message.mail_date", [], $locale);
        $mailYouFollowingUrl = \App\Support\Locale::trans("message.mail_use_following_url", [], $locale);
        $mailHere = \App\Support\Locale::trans("message.mail_here", [], $locale);
        $mailYouFollowingUrl1 = \App\Support\Locale::trans("message.mail_use_following_url_1", [], $locale);
        $mailYours = \App\Support\Locale::trans("message.mail_yours", [], $locale);
        $siteName = \App\Models\Setting::getSiteName();
        $mailTheSiteTeam = sprintf(\App\Support\Locale::trans("message.mail_the_site_team", [], $locale), $siteName);
		$body = <<<EOD
		{$mailDear}$msg_receiver,

		{$mailYouReceivedAPm}

		{$mailSender}: $username
		{$mailSubject}: $subject
		{$mailDate}: $date

		{$mailYouFollowingUrl}<b><a href="javascript:void(null)" onclick="window.open('$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid')">{$mailHere}</a></b>{$mailYouFollowingUrl1}<br />
$prefix$BASEURL/messages.php?action=viewmessage&id=$msgid

		------{$mailYours}
		{$mailTheSiteTeam}
EOD;

		\App\Support\Mail::sentLegacy((string) $user["email"], (string) $SITENAME, (string) $SITEEMAIL, (string) $title, (string) str_replace("<br />", "<br />", nl2br($body)), (string) "sendmessage", (bool) false, (bool) false, '', (string) 'UTF-8');

	}
}
	$delete = \App\Support\SupportContext::getPost("delete");

	if ($origmsg)
	{
		if ($delete == "yes")
		{
			// Make sure receiver of $origmsg is current user
			$orig = \App\Models\Message::query()->find($origmsg);
			if ($orig)
			{
				if ($orig->receiver != $CURUSER["id"])
				\App\Support\LegacyResponse::abort("w00t", "This shouldn't happen.");
				if ($orig->saved == "no")
				$orig->delete();
				elseif ($orig->saved == "yes")
				$orig->update(['location' => '0']);

			}
		}
		if (!$returnto)
		$returnto = "" . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/messages.php";
	}

	if ($returnto)
	{
		\App\Support\LegacyResponse::redirect($returnto);
	}

	\App\Support\LegacyResponse::abort(
		$lang_takemessage['std_succeeded'],
		(($n_pms > 1) ? "$n".$lang_takemessage['std_messages_out_of']."$n_pms".$lang_takemessage['std_were'] : $lang_takemessage['std_message_was']).
	$lang_takemessage['std_successfully_sent'] . ($l ? " $l profile comment" . (($l>1) ? $lang_takemessage['std_s_were'] : $lang_takemessage['std_was']) . $lang_takemessage['std_updated'] : "")
	);
?>