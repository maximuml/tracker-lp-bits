<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
return;
$id = intval(\App\Support\SupportContext::getQuery("id") ?? 0);
\App\Support\LegacyResponse::assertId($id, true);

$user = \App\Models\User::query()->where('id', $id)->first(['username', 'class', 'email']);
if (!$user) \App\Support\LegacyResponse::abort("Error", "No such user.");
$arr = $user->toArray();
$username = $arr["username"];
if ($arr["class"] < UC_MODERATOR)
	\App\Support\LegacyResponse::abort("Error", "The gateway can only be used to e-mail staff members.");

if ($__server_REQUEST_METHOD == "POST")
{
	$to = $arr["email"];
	$from = substr(htmlspecialchars(trim(\App\Support\SupportContext::getPost("from"))), 0, 80);
	if ($from == "") $from = "Anonymous";

	$from_email = substr(htmlspecialchars(trim(\App\Support\SupportContext::getPost("from_email"))), 0, 80);
	if ($from_email == "") $from_email = "".$SITEEMAIL."";
	$from_email =  \App\Support\Email::sanitizeForDisplay((string) $from_email);
	if (!$from_email)
    	\App\Support\LegacyResponse::abort("Error", "You must enter an email address!");
	if (!\App\Support\Email::isWellFormed((string) $from_email))
  	\App\Support\LegacyResponse::abort("Error", "Invalid email address!");
	$from = "$from <$from_email>";

	$subject = substr(htmlspecialchars(trim(\App\Support\SupportContext::getPost("subject"))), 0, 80);
	if ($subject == "") $subject = "(No subject)";
	$subject = "Fw: $subject";

	$message = htmlspecialchars(trim(\App\Support\SupportContext::getPost("message")));
	if ($message == "") \App\Support\LegacyResponse::abort("Error", "No message text!");

	$message = "Message submitted from ".\App\Support\Network::clientIp()." at " . date("Y-m-d H:i:s") . ".\n" .
		"Note: By replying to this e-mail you will reveal your e-mail address.\n" .
		"---------------------------------------------------------------------\n\n" .
		$message . "\n\n" .
		"---------------------------------------------------------------------\n$SITENAME E-Mail Gateway\n";

	$success = \App\Support\Mail::sentLegacy((string) $to, (string) $from, (string) $from_email, (string) $subject, (string) $message, (string) "E-Mail Gateway", (bool) false, (bool) false, '', (string) 'UTF-8');

	if ($success)
		\App\Support\LegacyResponse::abort("Success", "E-mail successfully queued for delivery.");
	else
		\App\Support\LegacyResponse::abort("Error", "The mail could not be sent. Please try again later.");
}

\App\Support\Html::stdhead("E-mail gateway");
?>
<p><table border=0 class=main cellspacing=0 cellpadding=0><tr>
<td class=embedded style='padding-left: 10px'><font size=3><b>Send e-mail to <?php echo $username;?></b></font></td>
</tr></table></p>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=email-gateway.php?id=<?php echo $id?>>
<tr><td class=rowhead>Your name</td><td><input type=text name=from size=80></td></tr>
<tr><td class=rowhead>Your e-mail</td><td><input type=text name=from_email size=80></td></tr>
<tr><td class=rowhead>Subject</td><td><input type=text name=subject size=80></td></tr>
<tr><td class=rowhead>Message</td><td><textarea name=message cols=80 rows=20></textarea></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send" class=btn></td></tr>
</form>
</table>
<p>
<font class=small><b>Note:</b> Your IP-address will be logged and visible to the recipient to prevent abuse.<br />
Make sure to supply a valid e-mail address if you expect a reply.</font>
</p>
<?php \App\Support\Html::stdfoot();