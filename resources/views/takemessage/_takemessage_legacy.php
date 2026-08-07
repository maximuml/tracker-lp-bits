<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
	stderr($lang_takemessage['std_error'], $lang_takemessage['std_permission_denied']);

	$origmsg = intval(\App\Support\SupportContext::getPost("origmsg") ?? 0);
	$msg = trim(\App\Support\SupportContext::getPost("body"));
	if (((\App\Support\SupportContext::getPost('forward') !== null)) && \App\Support\SupportContext::getPost('forward') == 1) //this is forwarding
	{
		if (!$origmsg)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_invalid_id']);
		$origmsgRecord = \App\Models\Message::query()->where('id', $origmsg)
		    ->where(function ($query) {
		        $query->where('receiver', \App\Support\SupportContext::getGlobal('CURUSER')['id'])
		              ->orWhere('sender', \App\Support\SupportContext::getGlobal('CURUSER')['id']);
		    })->first();
		if (!$origmsgRecord)
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_no_permission_forwarding']);
		$origmsgrow = $origmsgRecord->toArray();
		if(!\App\Support\SupportContext::getPost('to'))
			stderr($lang_takemessage['std_error'], $lang_takemessage['std_must_enter_username']);
		$receiver = get_user_id_from_name(trim(\App\Support\SupportContext::getPost('to')));
        $locale = get_user_locale($receiver);
		if ($origmsgrow['sender'] == 0)
		{
			$origfrom = nexus_trans("message.msg_system", [], $locale);
		}
		else
		{
			$origmsgsendername = get_plain_username($origmsgrow['sender']);
			$origfrom = "[url=userdetails.php?id=".$origmsgrow['sender']."]".$origmsgsendername."[/url]";
		}
		$msg = "-------- ".nexus_trans("message.msg_original_message_from", [], $locale) . $origfrom . " --------\n" . $origmsgrow['msg']."\n\n".($msg ? "-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n".$msg : "");

	}
	else
	{
		$receiver = intval(\App\Support\SupportContext::getPost("receiver") ?? 0);
		if (!is_valid_id($receiver) || ($origmsg && !is_valid_id($origmsg)))
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_invalid_id']);
		$bodyadd = "";
		if (!$msg)
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_please_enter_something']);
	}
	$save = \App\Support\SupportContext::getPost("save");
	$returnto = \App\Support\SupportContext::getPost("returnto");

	// Anti Flood Code
	// This code ensures that a member can only send one PM every 10 seconds.
	if (!user_can('staffmem')) {
		if (strtotime($CURUSER['last_pm']) > (TIMENOW - 10))
		{
			$secs = 60 - (TIMENOW - strtotime($CURUSER['last_pm']));
			stderr($lang_takemessage['std_error'],$lang_takemessage['std_message_flooding_denied'].$secs.$lang_takemessage['std_before_sending_pm']);
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
		stderr($lang_takemessage['std_error'], $lang_takemessage['std_user_not_exist']);
	$user = (array) $user;

	//Make sure recipient wants this message
	if (!user_can('staffmem'))
	{
		if ($user["parked"] == "yes")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_account_parked']);
		if ($user["acceptpms"] == "yes")
		{
			$blocked = \Nexus\Database\NexusDB::table('blocks')
			    ->where('userid', $receiver)
			    ->where('blockid', $CURUSER["id"])
			    ->count() > 0;
			if ($blocked)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_your_pms']);
		}
		elseif ($user["acceptpms"] == "friends")
		{
			$isFriend = \Nexus\Database\NexusDB::table('friends')
			    ->where('userid', $receiver)
			    ->where('friendid', $CURUSER["id"])
			    ->count() > 0;
			if (!$isFriend)
			stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_accepts_friends_pms']);
		}
		elseif ($user["acceptpms"] == "no")
		stderr($lang_takemessage['std_refused'], $lang_takemessage['std_user_blocks_all_pms']);
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
		$prefix = get_protocol_prefix();
        $locale = get_user_locale($user['id']);
		$title = "$SITENAME ".nexus_trans("message.mail_received_pm_from", [], $locale) . $username . "!";
        $mailDear = nexus_trans("message.mail_dear", [], $locale);
        $mailYouReceivedAPm = nexus_trans("message.mail_you_received_a_pm", [], $locale);
        $mailSender = nexus_trans("message.mail_sender", [], $locale);
        $mailSubject = nexus_trans("message.mail_subject", [], $locale);
        $mailDate = nexus_trans("message.mail_date", [], $locale);
        $mailYouFollowingUrl = nexus_trans("message.mail_use_following_url", [], $locale);
        $mailHere = nexus_trans("message.mail_here", [], $locale);
        $mailYouFollowingUrl1 = nexus_trans("message.mail_use_following_url_1", [], $locale);
        $mailYours = nexus_trans("message.mail_yours", [], $locale);
        $siteName = \App\Models\Setting::getSiteName();
        $mailTheSiteTeam = sprintf(nexus_trans("message.mail_the_site_team", [], $locale), $siteName);
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

		sent_mail($user["email"],$SITENAME,$SITEEMAIL,$title,str_replace("<br />","<br />",nl2br($body)),"sendmessage",false,false,'');

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
				stderr("w00t","This shouldn't happen.");
				if ($orig->saved == "no")
				$orig->delete();
				elseif ($orig->saved == "yes")
				$orig->update(['location' => '0']);

			}
		}
		if (!$returnto)
		$returnto = "" . get_protocol_prefix() . "$BASEURL/messages.php";
	}

	if ($returnto)
	{
		header("Location: $returnto");
		die;
	}

	stdhead();
	stdmsg($lang_takemessage['std_succeeded'], (($n_pms > 1) ? "$n".$lang_takemessage['std_messages_out_of']."$n_pms".$lang_takemessage['std_were'] : $lang_takemessage['std_message_was']).
	$lang_takemessage['std_successfully_sent'] . ($l ? " $l profile comment" . (($l>1) ? $lang_takemessage['std_s_were'] : $lang_takemessage['std_was']) . $lang_takemessage['std_updated'] : ""));
stdfoot();
exit;
?>
