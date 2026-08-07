<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function puke()
{
$CURUSER = \App\Support\SupportContext::getUser() ?? [];
	$msg = "User ".$CURUSER["username"]." (id: ".$CURUSER["id"].") is hacking user's profile. IP : ".getip();
	write_log($msg,'mod');
	stderr("Error", "Permission denied. For security reason, we logged this action");
}

if (!user_can('prfmanage'))
	puke();

$action = \App\Support\SupportContext::getPost("action");
if ($action == "confirmuser")
{
	$userid = (int)\App\Support\SupportContext::getPost("userid");
	$confirm = \App\Support\SupportContext::getPost("confirm");
	\App\Repositories\ModtaskRepository::confirmUser($userid, $confirm);
	header("Location: " . get_protocol_prefix() . "$BASEURL/unco.php?status=1");
	return;
}
if ($action == "edituser")
{
	$userid = \App\Support\SupportContext::getPost("userid");
	$userInfo = \App\Models\User::query()->findOrFail($userid);
//	$class = intval(\App\Support\SupportContext::getPost("class") ?? 0);
	$class = $userInfo->class;
    $locale = get_user_locale($userid);
//	$vip_added = (\App\Support\SupportContext::getPost("vip_added") == 'yes' ? 'yes' : 'no');
    $vip_added = $userInfo->vip_added;
//	$vip_until = !empty(\App\Support\SupportContext::getPost("vip_until")) ? \App\Support\SupportContext::getPost('vip_until') : null;
    $vip_until = $userInfo->vip_until;

	$warned = \App\Support\SupportContext::getPost("warned") ?? '';
	$warnlength = intval(\App\Support\SupportContext::getPost("warnlength") ?? 0);
	$warnpm = \App\Support\SupportContext::getPost("warnpm") ?? '';
	$title = \App\Support\SupportContext::getPost("title") ?? '';
	$avatar = \App\Support\SupportContext::getPost("avatar") ?? '';
	$signature = \App\Support\SupportContext::getPost("signature") ?? '';

	$enabled = \App\Support\SupportContext::getPost("enabled") ?? 'yes';
	$uploadpos = \App\Support\SupportContext::getPost("uploadpos") ?? 'yes';
	$downloadpos = \App\Support\SupportContext::getPost("downloadpos") ?? 'yes';
	$privacy = \App\Support\SupportContext::getPost("privacy") ?? 'normal';
	$forumpost = \App\Support\SupportContext::getPost("forumpost") ?? 'yes';
	$chpassword = \App\Support\SupportContext::getPost("chpassword") ?? '';
	$passagain = \App\Support\SupportContext::getPost("passagain") ?? '';

	$supportlang = \App\Support\SupportContext::getPost("supportlang") ?? '';
	$support = \App\Support\SupportContext::getPost("support") ?? 'no';
	$supportfor = \App\Support\SupportContext::getPost("supportfor") ?? '';

	$moviepicker = \App\Support\SupportContext::getPost("moviepicker") ?? 'no';
	$pickfor = \App\Support\SupportContext::getPost("pickfor") ?? '';
	$stafffor = \App\Support\SupportContext::getPost("staffduties") ?? '';

	if (!is_valid_id($userid) || !is_valid_user_class($class))
		stderr("Error", "Bad user ID or class ID.");
	if (get_user_class() <= $class)
		stderr("Error", "You have no permission to change user's class to ".get_user_class_name($class,false,false,true).". BTW, how do you get here?");
	$arr = \App\Repositories\ModtaskRepository::getUserArray($userid) ?? puke();

	$curenabled = $arr["enabled"];
	$curparked = $arr["parked"];
	$curuploadpos = $arr["uploadpos"];
	$curdownloadpos = $arr["downloadpos"];
	$curforumpost = $arr["forumpost"];
	$curclass = $arr["class"];
	$curwarned = $arr["warned"];

	$updateset = [];
	$updateset['stafffor'] = $stafffor;
	$updateset['pickfor'] = $pickfor;
	$updateset['picker'] = $moviepicker;
	//migrate to management
//	$updateset[] = "enabled = " . sqlesc($enabled);
	$updateset['uploadpos'] = $uploadpos;
	$updateset['downloadpos'] = $downloadpos;
	$updateset['forumpost'] = $forumpost;
	$updateset['avatar'] = $avatar;
	$updateset['signature'] = $signature;
	$updateset['title'] = $title;
	$updateset['support'] = $support;
	$updateset['supportfor'] = $supportfor;
	$updateset['supportlang'] = $supportlang;
    $banLog = [];
    $userModifyLogs = [];

//	if(!user_can('cruprfmanage'))
//	{
//		$modcomment = $arr["modcomment"];
//	}
	if(user_can('cruprfmanage'))
	{
		$email = \App\Support\SupportContext::getPost("email") ?? '';
		$username = \App\Support\SupportContext::getPost("username") ?? '';
		$modcomment = \App\Support\SupportContext::getPost("modcomment") ?? '';
		$downloaded = \App\Support\SupportContext::getPost("downloaded") ?? 0;
		$ori_downloaded = \App\Support\SupportContext::getPost("ori_downloaded") ?? 0;
		$uploaded = \App\Support\SupportContext::getPost("uploaded") ?? 0;
		$ori_uploaded = \App\Support\SupportContext::getPost("ori_uploaded") ?? 0;
		$bonus = \App\Support\SupportContext::getPost("bonus") ?? 0;
		$ori_bonus = \App\Support\SupportContext::getPost("ori_bonus") ?? 0;
		$invites = \App\Support\SupportContext::getPost("invites") ?? 0;
		$added = date("Y-m-d H:i:s");
		if ($arr['email'] != $email){
			$updateset['email'] = $email;
//			$modcomment = date("Y-m-d") . " - Email changed from $arr[email] to $email by {$CURUSER['username']}.\n". $modcomment;
			$modifyLog = "Email changed from $arr[email] to $email by {$CURUSER['username']}.";
            do_log($modifyLog, "alert");
            $userModifyLogs[] = $modifyLog;
            $locale = get_user_locale($userid);
			$subject = nexus_trans("user.msg_email_change", [], $locale);
			$msg = nexus_trans("user.msg_your_email_changed_from", [], $locale).$arr['email'].nexus_trans("user.msg_to_new", [], $locale) . $email .nexus_trans("user.msg_by", [], $locale).$CURUSER['username'];

			\App\Models\Message::add([
			    'sender' => 0,
			    'receiver' => $userid,
			    'subject' => $subject,
			    'msg' => $msg,
			    'added' => now(),
			]);
		}
		if ($arr['username'] != $username){
			$updateset['username'] = $username;
//			$modcomment = date("Y-m-d") . " - Username changed from {$arr['username']} to $username by {$CURUSER['username']}.\n". $modcomment;
			$userModifyLogs[] = "Username changed from {$arr['username']} to $username by {$CURUSER['username']}";

            $subject = nexus_trans("user.msg_username_change", [], $locale);
			$msg = nexus_trans("user.msg_your_username_changed_from", [], $locale).$arr['username'].nexus_trans("user.msg_to_new", [], $locale) . $username .nexus_trans("user.msg_by", [], $locale).$CURUSER['username'];

			\App\Models\Message::add([
			    'sender' => 0,
			    'receiver' => $userid,
			    'subject' => $subject,
			    'msg' => $msg,
			    'added' => now(),
			]);

			$changeLog = [
			    'uid' => $arr['id'],
			    'operator' => $CURUSER['username'],
                'change_type' => \App\Models\UsernameChangeLog::CHANGE_TYPE_ADMIN,
                'username_old' => $arr['username'],
                'username_new' => $username,
            ];
			\App\Models\UsernameChangeLog::query()->create($changeLog);
		}
        //migrate to management
//		if ($ori_downloaded != $downloaded){
//			$updateset[] = "downloaded = " . sqlesc($downloaded);
//			$modcomment = date("Y-m-d") . " - Downloaded amount changed from $arr[downloaded] to $downloaded by {$CURUSER['username']}.\n". $modcomment;
//			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_downloaded_change']);
//			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_downloaded_changed_from'].mksize($arr['downloaded']).$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . mksize($downloaded) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
//			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//		}
//
//		if ($ori_uploaded != $uploaded){
//			$updateset[] = "uploaded = " . sqlesc($uploaded);
//			$modcomment = date("Y-m-d") . " - Uploaded amount changed from $arr[uploaded] to $uploaded by {$CURUSER['username']}.\n". $modcomment;
//			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_uploaded_change']);
//			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_uploaded_changed_from'].mksize($arr['uploaded']).$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . mksize($uploaded) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
//			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//		}
//		if ($ori_bonus != $bonus){
//			$updateset[] = "seedbonus = " . sqlesc($bonus);
//			$modcomment = date("Y-m-d") . " - Bonus amount changed from $arr[seedbonus] to $bonus by {$CURUSER['username']}.\n". $modcomment;
//			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_bonus_change']);
//			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_bonus_changed_from'].$arr['seedbonus'].$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . $bonus .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
//			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//		}
//		if ($arr['invites'] != $invites){
//			$updateset[] = "invites = " . sqlesc($invites);
//			$modcomment = date("Y-m-d") . " - Invite amount changed from $arr[invites] to $invites by {$CURUSER['username']}.\n". $modcomment;
//			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_invite_change']);
//			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_invite_changed_from'].$arr['invites'].$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . $invites .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
//			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//		}
	}
	if(get_user_class() == UC_STAFFLEADER)
	{
		$donor = \App\Support\SupportContext::getPost("donor");
		$donoruntil = !empty(\App\Support\SupportContext::getPost('donoruntil')) ? \App\Support\SupportContext::getPost('donoruntil') : null;
		$donated = \App\Support\SupportContext::getPost("donated");
		$donated_cny = \App\Support\SupportContext::getPost("donated_cny");
		$this_donated_usd = $donated - $arr["donated"];
		$this_donated_cny = $donated_cny - $arr["donated_cny"];
		$memo = htmlspecialchars(\App\Support\SupportContext::getPost("donation_memo"));

		if ($donated != $arr['donated'] || $donated_cny != $arr['donated_cny']) {
			\App\Repositories\ModtaskRepository::addFund($userid, (float)$this_donated_usd, (float)$this_donated_cny, $memo);
			$updateset['donated'] = $donated;
			$updateset['donated_cny'] = $donated_cny;
		}
		$updateset['donor'] = $donor;
		$updateset['donoruntil'] = $donoruntil;

		if (($donor != $arr['donor']) && (($donor == 'yes' && $donoruntil && $donoruntil >= date('Y-m-d H:i:s')) || ($donor == 'no'))) {
            $subject = nexus_trans("user.msg_your_donor_status_changed", [], $locale);
            $msg = nexus_trans("user.msg_donor_status_changed_by", [], $locale).$CURUSER['username'];
            $added = date("Y-m-d H:i:s");

			\App\Models\Message::add([
			    'sender' => 0,
			    'receiver' => $userid,
			    'subject' => $subject,
			    'msg' => $msg,
			    'added' => now(),
			]);

//            $modcomment = date("Y-m-d") . " - donor status changed by {$CURUSER['username']}. Current donor status: $donor \n". $modcomment;
            $userModifyLogs[] = "donor status changed by {$CURUSER['username']}. Current donor status: $donor";
        }
	}
//migrate to management
//	if ($chpassword != "" AND $passagain != "") {
//		unset($passupdate);
//		$passupdate=false;
//
//		if ($chpassword ==  $username OR strlen($chpassword) > 40 OR strlen($chpassword) < 6 OR $chpassword != $passagain)
//			$passupdate=false;
//		else
//			$passupdate=true;
//	}
//
//	if ((isset($passupdate)) && $passupdate) {
//		$sec = mksecret();
//		$passhash = md5($sec . $chpassword . $sec);
//		$updateset[] = "secret = " . sqlesc($sec);
//		$updateset[] = "passhash = " . sqlesc($passhash);
//	}

	if ($curclass >= get_user_class())
		puke();

    //migrate to management
//	if (user_can('user-change-class') && $curclass != $class)
//	{
//		$what = ($class > $curclass ? $lang_modtask_target[get_user_lang($userid)]['msg_promoted'] : $lang_modtask_target[get_user_lang($userid)]['msg_demoted']);
//		$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_class_change']);
//		$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_you_have_been'].$what.$lang_modtask_target[get_user_lang($userid)]['msg_to'] . get_user_class_name($class) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
//		$added = date("Y-m-d H:i:s");
//		sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
//		$updateset[] = "class = $class";
//		$what = ($class > $curclass ? "Promoted" : "Demoted");
//		$modcomment = date("Y-m-d") . " - $what to '" . get_user_class_name($class) . "' by {$CURUSER['username']}.\n". $modcomment;
//	}
//	if ($class == UC_VIP)
//	{
//		$updateset[] = "vip_added = ".sqlesc($vip_added);
//		if ($vip_added == 'yes')
//			$updateset[] = "vip_until = ".sqlesc($vip_until);
//		$subject = nexus_trans("user.msg_your_vip_status_changed", [], $locale);
//		$msg = nexus_trans("user.msg_vip_status_changed_by", [], $locale).$CURUSER['username'];
//		$added = date("Y-m-d H:i:s");
//
//		\App\Models\Message::add([
//		    'sender' => 0,
//		    'receiver' => $userid,
//		    'subject' => $subject,
//		    'msg' => $msg,
//		    'added' => now(),
//		]);
//
////		$modcomment = date("Y-m-d") . " - VIP status changed by {$CURUSER['username']}. VIP added: ".$vip_added.($vip_added == 'yes' ? "; VIP until: ".$vip_until : "").".\n". $modcomment;
//        $userModifyLogs[] = "VIP status changed by {$CURUSER['username']}. VIP added: ".$vip_added.($vip_added == 'yes' ? "; VIP until: ".$vip_until : "");
//	}

	if ($warned && $curwarned != $warned)
	{
		$updateset['warned'] = $warned;
		$updateset['warneduntil'] = null;

		if ($warned == 'no')
		{
//			$modcomment = date("Y-m-d") . " - Warning removed by {$CURUSER['username']}.\n". $modcomment;
            $userModifyLogs[] = "Warning removed by {$CURUSER['username']}";
			$subject = nexus_trans("user.msg_warn_removed", [], $locale);
			$msg = nexus_trans("user.msg_your_warning_removed_by", [], $locale) . $CURUSER['username'] . ".";
		}

		$added = date("Y-m-d H:i:s");
		//sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES (0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
		\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
		]);
	}
	elseif ($warnlength)
	{
		if ($warnlength == 255)
		{
//			$modcomment = date("Y-m-d") . " - Warned by " . $CURUSER['username'] . ".\nReason: $warnpm.\n". $modcomment;
            $userModifyLogs[] = "Warned by " . $CURUSER['username'] . ".\nReason: $warnpm.";

			$msg = nexus_trans("user.msg_you_are_warned_by", [], $locale).$CURUSER['username']."." . ($warnpm ? nexus_trans("user.msg_reason", [], $locale).$warnpm : "");
			$updateset['warneduntil'] = null;
		}else{
			$warneduntil = date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + $warnlength * 604800));
			$dur = $warnlength . nexus_trans("user.msg_week", [], $locale) . ($warnlength > 1 ? nexus_trans("user.msg_s", [], $locale) : "");
			$msg = nexus_trans("user.msg_you_are_warned_for", [], $locale).$dur.nexus_trans("user.msg_by", [], $locale)  . $CURUSER['username'] . "." . ($warnpm ? nexus_trans("user.msg_reason", [], $locale).$warnpm : "");
//			$modcomment = date("Y-m-d") . " - Warned for $dur by " . $CURUSER['username'] .  ".\nReason: $warnpm.\n". $modcomment;
            $userModifyLogs[] = "Warned for $dur by " . $CURUSER['username'] .  ".Reason: $warnpm";
			$updateset['warneduntil'] = $warneduntil;
		}
		$subject = nexus_trans("user.msg_you_are_warned", [], $locale);
		$added = date("Y-m-d H:i:s");

		\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
		]);

		$updateset['warned'] = 'yes';
		$updateset['lastwarned'] = now()->toDateTimeString();
		$updateset['warnedby'] = (int)$CURUSER['id'];
		$updateset['timeswarned'] = new \Illuminate\Database\Query\Expression('timeswarned + 1');
	}
	//migrate to management
//	if ($enabled != $curenabled)
//	{
//		if ($enabled == 'yes') {
//			$modcomment = date("Y-m-d") . " - Enabled by " . $CURUSER['username']. ".\n". $modcomment;
//			if (get_single_value("users","class","WHERE id = ".sqlesc($userid)) == UC_PEASANT){
//				$length = 30*86400; // warn users until 30 days
//				$until = sqlesc(date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + $length)));
//				sql_query("UPDATE users SET enabled='yes', leechwarn='yes', leechwarnuntil=$until WHERE id = ".sqlesc($userid));
//			}
//			else{
//				sql_query("UPDATE users SET enabled='yes', leechwarn='no' WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
//			}
//		} else {
//			$modcomment = date("Y-m-d") . " - Disabled by " . $CURUSER['username']. ".\n". $modcomment;
//			$banLog = [
//			    'uid' => $userid,
//                'username' => $user->username,
//                'operator' => $CURUSER['id'],
//                'reason' => nexus_trans('user.edit_ban_reason', [], $user->locale),
//            ];
//		}
//	}
	if ($privacy == "low" OR $privacy == "normal" OR $privacy == "strong")
		$updateset['privacy'] = $privacy;

	if (((\App\Support\SupportContext::getPost("resetkey") !== null)) && \App\Support\SupportContext::getPost("resetkey") == "yes")
	{
		$newpasskey = md5($arr['username'].date("Y-m-d H:i:s").$arr['passhash']);
		$updateset['passkey'] = $newpasskey;
	}
	if ($forumpost != $curforumpost)
	{
		if ($forumpost == 'yes')
		{
//			$modcomment = date("Y-m-d") . " - Posting enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Posting enabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_posting_rights_restored", [], $locale);
			$msg = nexus_trans("user.msg_your_posting_rights_restored", [], $locale). $CURUSER['username'] . nexus_trans("user.msg_you_can_post", [], $locale);
			$added = date("Y-m-d H:i:s");
			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
		else
		{
//			$modcomment = date("Y-m-d") . " - Posting disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Posting disabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_posting_rights_removed", [], $locale);
			$msg = nexus_trans("user.msg_your_posting_rights_removed", [], $locale) . $CURUSER['username'] . nexus_trans("user.msg_probable_reason", [], $locale);
			$added = date("Y-m-d H:i:s");
			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
	}
	if ($uploadpos != $curuploadpos)
	{
		if ($uploadpos == 'yes')
		{
//			$modcomment = date("Y-m-d") . " - Upload enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Upload enabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_upload_rights_restored", [], $locale);
			$msg = nexus_trans("user.msg_your_upload_rights_restored", [], $locale) . $CURUSER['username'] . nexus_trans("user.msg_you_upload_can_upload", [], $locale);
			$added = date("Y-m-d H:i:s");
			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
		else
		{
//			$modcomment = date("Y-m-d") . " - Upload disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Upload disabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_upload_rights_removed", [], $locale);
			$msg = nexus_trans("user.msg_your_upload_rights_removed", [], $locale) . $CURUSER['username'] . nexus_trans("user.msg_probably_reason_two", [], $locale);
			$added = date("Y-m-d H:i:s");
			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
	}
	if ($downloadpos != $curdownloadpos)
	{
		if ($downloadpos == 'yes')
		{
//			$modcomment = date("Y-m-d") . " - Download enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Download enabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_download_rights_restored", [], $locale);
			$msg = nexus_trans("user.msg_your_download_rights_restored", [], $locale). $CURUSER['username'] . nexus_trans("user.msg_you_can_download", [], $locale);
			$added = date("Y-m-d H:i:s");

			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
		else
		{
//			$modcomment = date("Y-m-d") . " - Download disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = "Download disabled by " . $CURUSER['username'];
			$subject = nexus_trans("user.msg_download_rights_removed", [], $locale);
			$msg = nexus_trans("user.msg_your_download_rights_removed", [], $locale) . $CURUSER['username'] . nexus_trans("user.msg_probably_reason_three", [], $locale);
			$added = date("Y-m-d H:i:s");

			\App\Models\Message::add([
		    'sender' => 0,
		    'receiver' => $userid,
		    'subject' => $subject,
		    'msg' => $msg,
		    'added' => now(),
			]);
		}
	}

//	$updateset[] = "modcomment = " . sqlesc($modcomment);
	\App\Repositories\ModtaskRepository::updateUser($userid, $updateset);
    if (!empty($banLog)) {
        \App\Models\UserBanLog::query()->insert($banLog);
    }
    if (!empty($userModifyLogs)) {
        $userModifyLogsInsert = [];
        foreach ($userModifyLogs as $userModifyLog) {
            $userModifyLogsInsert[] = [
                "user_id" => $userid,
                "content" => $userModifyLog,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            ];
        }
        \App\Models\UserModifyLog::query()->insert($userModifyLogsInsert);
    }
    clear_user_cache($userid, $userInfo->passkey);
	$returnto = htmlspecialchars(\App\Support\SupportContext::getPost("returnto"));
	header("Location: " . get_protocol_prefix() . "$BASEURL/$returnto");
	return;
}
puke();
?>
