<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (\App\Support\UserDisplay::currentClass() < UC_SYSOP)
	permissiondenied();

$action = ((\App\Support\SupportContext::getPost('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('action')) : '';

if ($action == "sendmail")
{
	$email = htmlspecialchars(trim(\App\Support\SupportContext::getPost('email')));
	$email = safe_email($email);
	if (!check_email($email))
		stderr($lang_mailtest['std_error'], $lang_mailtest['std_invalid_email_address']);
	$title = $SITENAME.$lang_mailtest['text_smtp_testing_mail'];
	$body = <<<EOD
{$lang_mailtest['mail_test_mail_content']}
EOD;

//	sent_mail($email, $SITENAME, $SITEEMAIL, change_email_encode(get_langfolder_cookie(), $title), change_email_encode(get_langfolder_cookie(),$body), '', false, false, '', get_email_encode(get_langfolder_cookie()));
//	sent_mail($email, $SITENAME, $SITEEMAIL, $title, $body, '', false, false, '', get_email_encode(get_langfolder_cookie()));
    $sendResult = sent_mail($email, $SITENAME, $SITEEMAIL, $title, $body, 'mailtest', false, false, '', 'UTF-8');
    if ($sendResult === true) {
        stderr($lang_mailtest['std_success'], $lang_mailtest['std_success_note']);
    } else {
        stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail'] . ' (SMTP disabled or mail not sent)', false);
    }
}
else
{
	stdhead($lang_mailtest['head_mail_test']);
	print("<h1 align=\"center\">".$lang_mailtest['text_mail_test']."</h1>");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
	print("<form method='post' action='mailtest.php'>");
	print("<input type='hidden' name='action' value='sendmail'>");
	tr($lang_mailtest['row_enter_email'], "<input type='text' name='email' size=35><br />".$lang_mailtest['text_enter_email_note'], 1);
	print("<tr><td colspan=\"2\" align=\"center\"><input type='submit' name='sendmail' value='".$lang_mailtest['submit_send_it']."'></td></tr>");
	print("</form></table>");
	stdfoot();
}
