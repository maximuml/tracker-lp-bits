<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_HTTP_REFERER = \App\Support\SupportContext::getServerValue('HTTP_REFERER');
$receiver = intval(\App\Support\SupportContext::getQuery("receiver") ?? 0);
	int_check($receiver,true);

	$replyto = \App\Support\SupportContext::getQuery("replyto") ?? '';
	if ($replyto && !is_valid_id($replyto))
		stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_permission_denied']);

	$user = \App\Models\User::query()->find($receiver);
	if (!$user)
		stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_no_user_id']);
	$subject = "";
	$body = "";
	if ($replyto)
	{
		$msg = \App\Models\Message::query()->find($replyto);
		if (!$msg)
			stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_permission_denied']);
		$msga = $msg->toArray();
		if ($msga["receiver"] != $CURUSER["id"])
			stderr($lang_sendmessage['std_error'],$lang_sendmessage['std_permission_denied']);
		$senderName = \App\Models\User::query()->where('id', $msga['sender'])->value('username');
		$body .= $msga['msg']."\n\n-------- [url=userdetails.php?id=".$CURUSER["id"]."]".$CURUSER["username"]."[/url][i] Wrote at ".date("Y-m-d H:i:s").":[/i] --------\n";
		$subject = $msga['subject'];
		if (preg_match('/^Re:\s/', $subject))
			$subject = preg_replace('/^Re:\s(.*)$/', 'Re(2): \\1', $subject);
		elseif (preg_match('/^Re\([0-9]*\):\s/', $msga['subject']))
		{
			$replycount=(int)preg_replace('/^Re\(([0-9]*)\):\s/', '\\1', $subject);
			$replycount++;
			$subject=preg_replace('/^Re\(([0-9]*)\):\s(.*)$/', 'Re('.$replycount.'): \\2', $subject);
		}
		else $subject = "Re: " . $msga['subject'];
		$subject = htmlspecialchars($subject);
	}
	stdhead($lang_sendmessage['head_send_message'], false);
	begin_main_frame();
	print("<form id=compose name=\"compose\" method=post action=takemessage.php>");
	print("<input type=hidden name=receiver value=".$receiver.">");
	if ((((\App\Support\SupportContext::getQuery("returnto") !== null)) && \App\Support\SupportContext::getQuery("returnto")) || $__server_HTTP_REFERER)
		print("<input type=hidden name=returnto value=\"".(htmlspecialchars(\App\Support\SupportContext::getQuery("returnto") ?? '') ? htmlspecialchars(\App\Support\SupportContext::getQuery("returnto")) : htmlspecialchars($__server_HTTP_REFERER))."\">");
	$title = $lang_sendmessage['text_message_to'].get_username($receiver);
	begin_compose($title, ($replyto ? "reply" : "new"), $body, true, $subject);
	print("<tr><td class=toolbox colspan=2 align=center>");
	if ($replyto) {
		print("<input type=checkbox name='delete' value='yes' ".($CURUSER['deletepms'] == 'yes' ? " checked" : "").">".$lang_sendmessage['checkbox_delete_message_replying_to']."<input type=hidden name=origmsg value=".$replyto.">");
	}

	print("<input type=checkbox name='save' value='yes' ". ($CURUSER['savepms'] == 'yes' ? " checked" : "").">".$lang_sendmessage['checkbox_save_message_to_sendbox']);
	print("</td></tr>");
	end_compose();
	end_main_frame();
	stdfoot();
?>
