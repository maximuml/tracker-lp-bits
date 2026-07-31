<?php

require_once("../include/bittorrent.php");
dbconn();
cur_user_check ();
//require_once(get_langfile_path("",true));
require_once(get_langfile_path("", false, get_langfolder_cookie()));
require_once(get_langfile_path("takeinvite.php"));

$isPreRegisterEmailAndUsername = get_setting("system.is_invite_pre_email_and_username") == "yes";

function bark($msg) {
	global $lang_takesignup;
	stdhead();
	stdmsg($lang_takesignup['std_signup_failed'], $msg);
	stdfoot();
	exit;
}

$type = $_POST['type'] ?? '';
if ($type == 'invite'){
registration_check();
failedloginscheck ("Invite Signup");
if ($iv == "yes")
	check_code ($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null,'signup.php?type=invite&invitenumber='.htmlspecialchars($_POST['hash']));
}
else{
registration_check("normal");
failedloginscheck ("Signup");
if ($iv == "yes")
	check_code ($_POST['imagehash'] ?? null, $_POST['imagestring'] ?? null);
}
function isportopen($port)
{
	$sd = @fsockopen($_SERVER["REMOTE_ADDR"], $port, $errno, $errstr, 1);
	if ($sd)
	{
		fclose($sd);
		return true;
	}
	else
		return false;
}

function isproxy()
{
	$ports = array(80, 88, 1075, 1080, 1180, 1182, 2282, 3128, 3332, 5490, 6588, 7033, 7441, 8000, 8080, 8085, 8090, 8095, 8100, 8105, 8110, 8888, 22788);
	for ($i = 0; $i < count($ports); ++$i)
		if (isportopen($ports[$i])) return true;
	return false;
}
if ($type=='invite')
{
$inviter =  $_POST["inviter"];
	int_check($inviter);
$code = unesc($_POST["hash"]);

//check invite code
	$inv = \App\Models\Invite::query()->where('valid', \App\Models\Invite::VALID_YES)->where('hash', $code)->first();
	if (!$inv)
		bark('invalid invite code');
	if ($inv->inviter != $inviter) {
        \App\Models\Invite::query()->where('id', $inv->id)->update(['valid' => \App\Models\Invite::VALID_NO]);
        stderr(nexus_trans('nexus.invalid_argument'), nexus_trans('invite.invalid_inviter'));
        exit();
    }

$ip = getip();


$invusername = \App\Models\User::query()->where('id', $inviter)->value('username') ?? '';
}
if (!mkglobal("wantusername:wantpassword:email")) {
    die();
}
if ($isPreRegisterEmailAndUsername && $type == 'invite' && !empty($inv["pre_register_username"]) && !empty($inv["pre_register_email"])) {
    $wantusername = $inv["pre_register_username"];
    $email = $inv["pre_register_email"];
}
$email = htmlspecialchars(trim($email));
$email = safe_email($email);
if (!check_email($email))
	bark($lang_takesignup['std_invalid_email_address']);

$country = $_POST["country"];
	int_check($country);


$gender =  htmlspecialchars(trim($_POST["gender"]));
$allowed_genders = array("Male","Female","male","female");
if (!in_array($gender, $allowed_genders, true))
	bark($lang_takesignup['std_invalid_gender']);

if (empty($wantusername) || empty($wantpassword) || empty($email) || empty($country) || empty($gender))
	bark($lang_takesignup['std_blank_field']);


if (strlen($wantusername) > 12)
	bark($lang_takesignup['std_username_too_long']);

//if ($wantpassword != $passagain)
//	bark($lang_takesignup['std_passwords_unmatched']);

//if (strlen($wantpassword) < 6)
//	bark($lang_takesignup['std_password_too_short']);
//
//if (strlen($wantpassword) > 40)
//	bark($lang_takesignup['std_password_too_long']);
//
//if ($wantpassword == $wantusername)
//	bark($lang_takesignup['std_password_equals_username']);

if (!validemail($email))
	bark($lang_takesignup['std_wrong_email_address_format']);

if (!validusername($wantusername))
	bark($lang_takesignup['std_invalid_username']);

// make sure user agrees to everything...
if ($_POST["rulesverify"] != "yes" || $_POST["faqverify"] != "yes" || $_POST["ageverify"] != "yes")
	stderr($lang_takesignup['std_signup_failed'], $lang_takesignup['std_unqualified']);

// check if email addy is already in use
if (\App\Models\User::query()->where('email', $email)->count() > 0)
  bark($lang_takesignup['std_email_address'].$email.$lang_takesignup['std_in_use']);


$secret = mksecret();
//$wantpasshash = md5($secret . $wantpassword . $secret);
$wantpasshash = hash('sha256', $secret . hash('sha256', $wantpassword));
$editsecret = ($verification == 'admin' ? '' : $secret);
$invite_count = (int) $invite_count;
$passkey = md5($wantusername.date("Y-m-d H:i:s").$wantpasshash);

$send_email = $email;
$country = (int)$country;
$gender = htmlspecialchars(trim($_POST["gender"]));
$sitelangid = (int)get_langid_from_langcookie();
$authKey = mksecret();

if (\App\Models\User::query()->where('username', $wantusername)->exists())
  bark($lang_takesignup['std_username_exists']);

$userData = [
    'username' => $wantusername,
    'passhash' => $wantpasshash,
    'passkey' => $passkey,
    'secret' => $secret,
    'auth_key' => $authKey,
    'editsecret' => $editsecret,
    'email' => $email,
    'country' => $country,
    'gender' => $gender,
    'status' => 'pending',
    'class' => $defaultclass_class,
    'invites' => $invite_count,
    'added' => date("Y-m-d H:i:s"),
    'last_access' => date("Y-m-d H:i:s"),
    'lang' => $sitelangid,
    'stylesheet' => $defcss,
    'uploaded' => ($iniupload_main > 0 ? $iniupload_main : 0),
];
if ($type == 'invite') {
    $userData['invited_by'] = (int)$inviter;
}

$id = \App\Models\User::query()->insertGetId($userData);
$userInfo = \App\Models\User::query()->find($id, \App\Models\User::$commonFields);
fire_event("user_created", $userInfo);
$tmpInviteCount = get_setting('main.tmp_invite_count');
if ($tmpInviteCount > 0) {
    $userRep = new \App\Repositories\UserRepository();
    $userRep->addTemporaryInvite(null, $id, 'increment', $tmpInviteCount, 7);
}

$dt = date("Y-m-d H:i:s");
$subject = $lang_takesignup['msg_subject'].$SITENAME."!";
$siteName = \App\Models\Setting::getSiteName();
$msg = \App\Models\MessageTemplate::forRegisterWelcome($userInfo->lang, ['username' => $userInfo->username]);
if (empty($msg)) {
    $msg = $lang_takesignup['msg_congratulations'].$wantusername.sprintf($lang_takesignup['msg_you_are_a_member'],$siteName, $siteName);
}
\App\Models\Message::add([
    'sender' => 0,
    'receiver' => $id,
    'subject' => $subject,
    'added' => $dt,
    'msg' => $msg,
]);

//write_log("User account $id ($wantusername) was created");
$user = \App\Models\User::query()->find($id, ['passhash', 'secret', 'editsecret', 'status']);
if ($user) {
    $user->makeVisible(['secret']);
    $row = $user->toArray();
} else {
    $row = [];
}
$psecret = md5($row['secret'] ?? '');
$ip = getip();
$usern = htmlspecialchars($wantusername);
$title = $SITENAME.$lang_takesignup['mail_title'];
$confirmUrl = getSchemeAndHttpHost() . "/confirm.php?id=$id&secret=$psecret";
$confirmResendUrl = getSchemeAndHttpHost() . "/confirm_resend.php";
$mailTwo = sprintf($lang_takeinvite['mail_two'], $siteName);
$mailFive = sprintf($lang_takeinvite['mail_five'], $siteName, $siteName, $REPORTMAIL, $siteName);
$body = <<<EOD
{$lang_takesignup['mail_one']}$usern{$mailTwo}($email){$lang_takesignup['mail_three']}$ip{$lang_takesignup['mail_four']}
<b><a href="javascript:void(null)" onclick="window.open($confirmUrl)">
{$lang_takesignup['mail_this_link']} </a></b><br />
$confirmUrl
{$lang_takesignup['mail_four_1']}
<b><a href="javascript:void(null)" onclick="window.open($confirmResendUrl)">{$lang_takesignup['mail_here']}</a></b><br />
$confirmResendUrl
<br />
{$mailFive}
EOD;

if ($type == 'invite')
{
    //don't forget to delete confirmed invitee's hash code from table invites
    //sql_query("DELETE FROM invites WHERE hash = '".mysql_real_escape_string($code)."'");
    // set invalid
    $update = [
        'valid' => \App\Models\Invite::VALID_NO,
        'invitee_register_uid' => $id,
        'invitee_register_email' => $_POST['email'],
        'invitee_register_username' => $_POST['wantusername'],
    ];
    \App\Models\Invite::query()->where('id', $inv['id'])->update($update);

    $dt = date("Y-m-d H:i:s");
    $locale = get_user_locale($inviter);
    $subject = nexus_trans("user.msg_invited_user_has_registered", [], $locale);
    $msg = nexus_trans("user.msg_user_you_invited", [],$locale).$wantusername.nexus_trans("user.msg_has_registered", [], $locale);
    //sql_query("UPDATE users SET uploaded = uploaded + 10737418240 WHERE id = $inviter"); //add 10GB to invitor's uploading credit
    \App\Models\Message::add([
        'sender' => 0,
        'receiver' => $inviter,
        'subject' => $subject,
        'added' => $dt,
        'msg' => $msg,
    ]);
    $Cache->delete_value('user_'.$inviter.'_unread_message_count');
    $Cache->delete_value('user_'.$inviter.'_inbox_count');
}

if ($verification == 'admin'){
	if ($type == 'invite')
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=inviter");
	else
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=adminactivate");
}
elseif ($verification == 'automatic' || $smtptype == 'none'){
	header("Location: " . get_protocol_prefix() . "$BASEURL/confirm.php?id=$id&secret=$psecret");
}
else{
	sent_mail($send_email,$SITENAME,$SITEEMAIL,$title,$body,"signup",false,false,'');
	header("Location: " . get_protocol_prefix() . "$BASEURL/ok.php?type=signup&email=" . rawurlencode($send_email));
}

?>
