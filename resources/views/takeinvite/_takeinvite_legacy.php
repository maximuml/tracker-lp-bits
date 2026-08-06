<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$langFile = get_langfile_path();
if (!is_file(ROOT_PATH . $langFile)) {
	$langFile = 'lang/en/lang_takeinvite.php';
}
require_once ROOT_PATH . $langFile;
$id = $CURUSER['id'];
$lockName = sprintf("takeinvite:%s", $id);
$lock = new \Nexus\Database\NexusLock($lockName, 10);
if (!$lock->get()) {
    $errMsg = nexus_trans("nexus.do_not_repeat");
    stderr($errMsg, $errMsg);
}
registration_check('invitesystem', true, false);
$userRep = new \App\Repositories\UserRepository();
try {
    $sendText = $userRep->getInviteBtnText($CURUSER['id']);
} catch (\Exception $exception) {
    stderr($lang_takeinvite['std_error'], $exception->getMessage());
}
function bark($msg) {
  global $lang_takeinvite;
  stdhead();
  stdmsg($lang_takeinvite['head_invitation_failed'], $msg);
  stdfoot();
  exit;
}
$email = unesc(htmlspecialchars(trim($_POST["email"])));
$email = safe_email($email);
$preRegisterUsername = $_POST['pre_register_username'] ?? '';
$isPreRegisterEmailAndUsername = get_setting("system.is_invite_pre_email_and_username") == "yes";
if (strlen($preRegisterUsername) > 12)
	bark($lang_takeinvite['std_username_too_long']);
if (!$email)
    bark($lang_takeinvite['std_must_enter_email']);
if (!check_email($email))
	bark($lang_takeinvite['std_invalid_email_address']);

$body = str_replace("<br />", "<br />", nl2br(trim(strip_tags($_POST["body"]))));
if(!$body)
	bark($lang_takeinvite['std_must_enter_personal_message']);

if ($isPreRegisterEmailAndUsername) {
    if (empty($preRegisterUsername)) {
        bark(nexus_trans("invite.require_pre_register_username"));
    }
    if (!validusername($preRegisterUsername)) {
        bark(nexus_trans("user.username_invalid", ["username" => $preRegisterUsername]));
    }
    $exists = \App\Models\User::query()->where('username', $preRegisterUsername)->exists();
    if ($exists) {
        bark(nexus_trans("user.username_already_exists", ["username" => $preRegisterUsername]));
    }
}


// check if email addy is already in use
if (\App\Models\User::query()->where('email', $email)->count() > 0)
  bark($lang_takeinvite['std_email_address'].htmlspecialchars($email).$lang_takeinvite['std_is_in_use']);
if (\App\Models\Invite::query()->where('invitee', $email)->count() > 0)
  bark($lang_takeinvite['std_invitation_already_sent_to'].htmlspecialchars($email).$lang_takeinvite['std_await_user_registeration']);

if (empty($_POST['hash'])) {
    bark($lang_takeinvite['std_must_select_invite']);
}
if ($_POST['hash'] == 'permanent') {
    $hash  = md5(mt_rand(1,10000).$CURUSER['username'].TIMENOW.$CURUSER['passhash']);
} else {
    $hashRecord = \App\Models\Invite::query()->where('inviter', $CURUSER['id'])->where('hash', $_POST['hash'])->first();
    if (!$hashRecord) {
        bark($lang_takeinvite['hash_not_exists']);
    }
    if ($hashRecord->invitee != '') {
        bark('hash '.$lang_takeinvite['std_is_in_use']);
    }
    if ($hashRecord->expired_at->lt(now())) {
        bark($lang_takeinvite['hash_expired']);
    }
    $hash = $_POST['hash'];
}

$title = $SITENAME.$lang_takeinvite['mail_tilte'];

$signupUrl = getSchemeAndHttpHost() . "/signup.php?type=invite&invitenumber=$hash";
$siteName = \App\Models\Setting::getSiteName();
$mailTwo = sprintf($lang_takeinvite['mail_two'], $siteName, $siteName);
$mailFour = sprintf($lang_takeinvite['mail_four'], $siteName);
$mailSix = sprintf($lang_takeinvite['mail_six'], $REPORTMAIL, $siteName);
$message = <<<EOD
{$lang_takeinvite['mail_one']}{$CURUSER['username']}{$mailTwo}
<b><a href="javascript:void(null)" onclick="window.open($signupUrl)">{$lang_takeinvite['mail_here']}</a></b><br />
$signupUrl
<br />{$lang_takeinvite['mail_three']}$invite_timeout{$mailFour}{$CURUSER['username']}{$lang_takeinvite['mail_five']}<br />
$body
<br /><br />{$mailSix}
EOD;

$sendResult = sent_mail($email,$SITENAME,$SITEEMAIL,$title,$message,"invitesignup",false,false,'');
//this email is sent only when someone give out an invitation
if ($sendResult === true) {
    if (isset($hashRecord)) {
        $update = [
            'invitee' => $email,
            'time_invited' => now(),
            'valid' => 1,
        ];
        if ($isPreRegisterEmailAndUsername) {
            $update["pre_register_email"] = $email;
            $update["pre_register_username"] = $preRegisterUsername;
        }
        $hashRecord->update($update);
    } else {
        $insert = [
            "inviter" => $id,
            "invitee" => $email,
            "hash" => $hash,
            "time_invited" => now()->toDateTimeString()
        ];
        if ($isPreRegisterEmailAndUsername) {
            $insert["pre_register_email"] = $email;
            $insert["pre_register_username"] = $preRegisterUsername;
        }
        \App\Models\Invite::query()->insert($insert);
//        sql_query("INSERT INTO invites (inviter, invitee, hash, time_invited) VALUES ('".mysql_real_escape_string($id)."', '".mysql_real_escape_string($email)."', '".mysql_real_escape_string($hash)."', " . sqlesc(date("Y-m-d H:i:s")) . ")");
        \App\Models\User::query()->where('id', $id)->decrement('invites');
    }
}
$lock->release();
header("Location: invite.php?id=".htmlspecialchars($id)."&sent=1");
