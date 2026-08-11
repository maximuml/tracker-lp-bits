<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function bonusarray($option = 0){
$onegbupload_bonus = \App\Support\SupportContext::getGlobal('onegbupload_bonus');
$fivegbupload_bonus = \App\Support\SupportContext::getGlobal('fivegbupload_bonus');
$tengbupload_bonus = \App\Support\SupportContext::getGlobal('tengbupload_bonus');
$oneinvite_bonus = \App\Support\SupportContext::getGlobal('oneinvite_bonus');
$customtitle_bonus = \App\Support\SupportContext::getGlobal('customtitle_bonus');
$vipstatus_bonus = \App\Support\SupportContext::getGlobal('vipstatus_bonus');
$basictax_bonus = \App\Support\SupportContext::getGlobal('basictax_bonus');
$taxpercentage_bonus = \App\Support\SupportContext::getGlobal('taxpercentage_bonus');
$lang_mybonus = (array) (\App\Support\SupportContext::getGlobal('lang_mybonus') ?? []);

	$results = [];
    //1.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $onegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 1073741824;
    $bonus['name'] = $lang_mybonus['text_uploaded_one'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
	$results[] = $bonus;

    //5.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $fivegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 5368709120;
    $bonus['name'] = $lang_mybonus['text_uploaded_two'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;


    //10.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $tengbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 10737418240;
    $bonus['name'] = $lang_mybonus['text_uploaded_three'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;

    //100.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = \App\Support\Config\SiteConfig::current()->bonus->hundredGbUpload();
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 107374182400;
    $bonus['name'] = $lang_mybonus['text_uploaded_four'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;

    //10.0 GB Downloaded
    $bonus = array();
    $bonus['points'] = \App\Support\Config\SiteConfig::current()->bonus->tenGbDownload();
    $bonus['art'] = 'traffic_downloaded';
    $bonus['menge'] = 10737418240;
    $bonus['name'] = $lang_mybonus['text_downloaded_ten_gb'];
    $bonus['description'] = $lang_mybonus['text_download_note'];
    $results[] = $bonus;

    //100.0 GB Downloaded
    $bonus = array();
    $bonus['points'] = \App\Support\Config\SiteConfig::current()->bonus->hundredGbDownload();
    $bonus['art'] = 'traffic_downloaded';
    $bonus['menge'] = 107374182400;
    $bonus['name'] = $lang_mybonus['text_downloaded_hundred_gb'];
    $bonus['description'] = $lang_mybonus['text_download_note'];
    $results[] = $bonus;

    //Invite
    if ($oneinvite_bonus > 0){
        $bonus = array();
        $bonus['points'] = $oneinvite_bonus;
        $bonus['art'] = 'invite';
        $bonus['menge'] = 1;
        $bonus['name'] = $lang_mybonus['text_buy_invite'];
        $bonus['description'] = $lang_mybonus['text_buy_invite_note'];
        $results[] = $bonus;
    }

    //Tmp Invite
    $tmpInviteBonus = \App\Models\BonusLogs::getBonusForBuyTemporaryInvite();
    if ($tmpInviteBonus > 0) {
        $bonus = array();
        $bonus['points'] = $tmpInviteBonus;
        $bonus['art'] = 'tmp_invite';
        $bonus['menge'] = 1;
        $bonus['name'] = $lang_mybonus['text_buy_tmp_invite'];
        $bonus['description'] = $lang_mybonus['text_buy_tmp_invite_note'];
        $results[] = $bonus;
    }

    //Custom Title
    $bonus = array();
    $bonus['points'] = $customtitle_bonus;
    $bonus['art'] = 'title';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_custom_title'];
    $bonus['description'] = $lang_mybonus['text_custom_title_note'];
    $results[] = $bonus;


    //VIP Status
    $bonus = array();
    $bonus['points'] = $vipstatus_bonus;
    $bonus['art'] = 'class';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_vip_status'];
    $bonus['description'] = $lang_mybonus['text_vip_status_note'];
    $results[] = $bonus;

    //Bonus Gift
    $bonus = array();
    $bonus['points'] = 100;
    $bonus['art'] = 'gift_1';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_bonus_gift'];
    $bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
    if ($basictax_bonus || $taxpercentage_bonus){
        $onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
        $bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].\App\Support\Strings::addS($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
    }
    $results[] = $bonus;



    //Attendance card
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyAttendanceCard();
    $bonus['art'] = 'attendance_card';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_attendance_card'];
    $bonus['description'] = $lang_mybonus['text_attendance_card_note'];
    $results[] = $bonus;

    //Rainbow ID
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyRainbowId();
    $bonus['art'] = 'rainbow_id';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_buy_rainbow_id'];
    $bonus['description'] = $lang_mybonus['text_buy_rainbow_id_note'];
    $results[] = $bonus;

    //Change username card
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyChangeUsernameCard();
    $bonus['art'] = 'change_username_card';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_buy_change_username_card'];
    $bonus['description'] = $lang_mybonus['text_buy_change_username_card_note'];
    $results[] = $bonus;

    //Donate
    $bonus = array();
    $bonus['points'] = 1000;
    $bonus['art'] = 'gift_2';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_charity_giving'];
    $bonus['description'] = $lang_mybonus['text_charity_giving_note'];
    $results[] = $bonus;


    //Cancel hit and run
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
    $bonus['art'] = 'cancel_hr';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
    $bonus['description'] = '<p>
            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
            <input type="number" name="hr_id" />
        </p>';
    $results[] = $bonus;

    //Buy medal
    //migrate to medal.php since v1.8
//    $medals = \App\Models\Medal::query()->where('get_type', \App\Models\Medal::GET_TYPE_EXCHANGE)->get();
//    foreach ($medals as $medal) {
//        $results[] = [
//            'points' => $medal->price,
//            'art' => 'buy_medal',
//            'menge' => 0,
//            'name' => $medal->name,
//            'description' => sprintf(
//                '<div style="display: flex;align-items: center"><div style="padding: 10px">%s</div><div><img src="%s" style="max-height: 120px"/></div></div><input type="hidden" name="medal_id" value="%s">',
//                $medal->description, $medal->image_large, $medal->id
//            ),
//            'medal_id' => $medal->id,
//        ];
//    }

    return $results;

//
//	switch ($option)
//	{
//		case 1: {//1.0 GB Uploaded
//			$bonus['points'] = $onegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 1073741824;
//			$bonus['name'] = $lang_mybonus['text_uploaded_one'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 2: {//5.0 GB Uploaded
//			$bonus['points'] = $fivegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 5368709120;
//			$bonus['name'] = $lang_mybonus['text_uploaded_two'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 3: {//10.0 GB Uploaded
//			$bonus['points'] = $tengbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 10737418240;
//			$bonus['name'] = $lang_mybonus['text_uploaded_three'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 4: {//Invite
//			$bonus['points'] = $oneinvite_bonus;
//			$bonus['art'] = 'invite';
//			$bonus['menge'] = 1;
//			$bonus['name'] = $lang_mybonus['text_buy_invite'];
//			$bonus['description'] = $lang_mybonus['text_buy_invite_note'];
//			break;
//			}
//		case 5: {//Custom Title
//			$bonus['points'] = $customtitle_bonus;
//			$bonus['art'] = 'title';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_custom_title'];
//			$bonus['description'] = $lang_mybonus['text_custom_title_note'];
//			break;
//			}
//		case 6: {//VIP Status
//			$bonus['points'] = $vipstatus_bonus;
//			$bonus['art'] = 'class';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_vip_status'];
//			$bonus['description'] = $lang_mybonus['text_vip_status_note'];
//			break;
//			}
//		case 7: {//Bonus Gift
//			$bonus['points'] = 25;
//			$bonus['art'] = 'gift_1';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_bonus_gift'];
//			$bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
//			if ($basictax_bonus || $taxpercentage_bonus){
//				$onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
//				$bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].add_s($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
//				}
//			break;
//			}
//		case 9: {
//			$bonus['points'] = 1000;
//			$bonus['art'] = 'gift_2';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_charity_giving'];
//			$bonus['description'] = $lang_mybonus['text_charity_giving_note'];
//			break;
//			}
//        case 10: {
//            $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
//            $bonus['art'] = 'cancel_hr';
//            $bonus['menge'] = 0;
//            $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
//            $bonus['description'] = '<p>
//            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
//            <input type="number" name="hr_id" />
//        </p>';
//            break;
//        }
//		default: break;
//	}
//	return $bonus;
}

$allBonus = bonusarray();
$lockSeconds = 10;
$lockText = sprintf($lang_mybonus['lock_text'], $lockSeconds);
if ($bonus_tweak == "disable" || $bonus_tweak == "disablesave")
	\App\Support\LegacyResponse::abort($lang_mybonus['std_sorry'], $lang_mybonus['std_karma_system_disabled'].($bonus_tweak == "disablesave" ? "<b>".$lang_mybonus['std_points_active']."</b>" : ""), false);

$action = htmlspecialchars(\App\Support\SupportContext::getQuery('action') ?? '');
$do = htmlspecialchars(\App\Support\SupportContext::getQuery('do') ?? '');
unset($msg);
if ((isset($do))) {
	if ($do == "upload")
	$msg = $lang_mybonus['text_success_upload'];
    elseif ($do == "download")
    $msg = $lang_mybonus['text_success_download'];
	elseif ($do == "invite")
	$msg = $lang_mybonus['text_success_invites'];
    elseif ($do == "tmp_invite")
        $msg = $lang_mybonus['text_success_tmp_invites'];
	elseif ($do == "vip")
	$msg =  $lang_mybonus['text_success_vip']."<b>".\App\Support\UserClass::name(UC_VIP,false,false,true)."</b>".$lang_mybonus['text_success_vip_two'];
	elseif ($do == "vipfalse")
	$msg =  $lang_mybonus['text_no_permission'];
	elseif ($do == "title")
	$msg = sprintf($lang_mybonus['text_success_custom_title'], $CURUSER['title']);
	elseif ($do == "transfer")
	$msg =  $lang_mybonus['text_success_gift'];
	elseif ($do == "charity")
	$msg =  $lang_mybonus['text_success_charity'];
    elseif ($do == "cancel_hr")
        $msg =  $lang_mybonus['text_success_cancel_hr'];
    elseif ($do == "buy_medal")
        $msg =  $lang_mybonus['text_success_buy_medal'];
    elseif ($do == "attendance_card")
        $msg =  $lang_mybonus['text_success_buy_attendance_card'];
    elseif ($do == "rainbow_id")
        $msg =  $lang_mybonus['text_success_buy_rainbow_id'];
    elseif ($do == "change_username_card")
        $msg =  $lang_mybonus['text_success_buy_change_username_card'];
    elseif ($do == 'duplicated')
        $msg = $lockText;
	else
	$msg = '';
}
	\App\Support\Html::stdhead($CURUSER['username'] . $lang_mybonus['head_karma_page']);

	$bonus = number_format($CURUSER['seedbonus'], 1);
if (!$action) {
	print("<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n");
	print("<tr><td class=\"colhead\" colspan=\"4\" align=\"center\"><font class=\"big\">".$SITENAME.$lang_mybonus['text_karma_system']."</font></td></tr>\n");
	if ($msg)
	print("<tr><td align=\"center\" colspan=\"4\"><font class=\"striking\"><b>". $msg ."</b></font></td></tr>");
?>
<tr><td class="text" align="center" colspan="4"><?php echo $lang_mybonus['text_exchange_your_karma']?><?php echo $bonus?><?php echo $lang_mybonus['text_for_goodies'] ?>
<br /><b><?php echo $lang_mybonus['text_no_buttons_note'] ?></b><br /><small style="color: orangered">(<?php echo $lockText ?>)</small></td></tr>
<?php

print("<tr><td class=\"colhead\" align=\"center\">".$lang_mybonus['col_option']."</td>".
"<td class=\"colhead\" align=\"left\">".$lang_mybonus['col_description']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_points']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_trade']."</td>".
"</tr>");


for ($i=0; $i < count($allBonus); $i++)
{
	$bonusarray = $allBonus[$i];
	if (
	    ($bonusarray['art'] == 'gift_1' && $bonusgift_bonus == 'no')
        || ($bonusarray['art'] == 'cancel_hr' && !\App\Models\HitAndRun::getIsEnabled())
    ) {
        continue;
    }
    $bonusarrray['points'] = floatval($bonusarray['points']);

	print("<tr>");
	print("<form action=\"?action=exchange\" method=\"post\">");
	print("<td class=\"rowhead_center\"><input type=\"hidden\" name=\"option\" value=\"".$i."\" /><b>".($i + 1)."</b></td>");
	if ($bonusarray['art'] == 'title'){ //for Custom Title!
	    $otheroption_title = "<input type=\"text\" name=\"title\" style=\"width: 200px\" maxlength=\"30\" />";
	    print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_titile'].$otheroption_title.$lang_mybonus['text_click_exchange']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}
	elseif ($bonusarray['art'] == 'gift_1'){  //for Give A Karma Gift
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\"><b>".$lang_mybonus['text_username']."</b><input type=\"text\" name=\"username\" style=\"width: 200px\" maxlength=\"24\" /></td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' min='100' />".$lang_mybonus['text_karma_points']."</td></tr><tr><td class=\"embedded\" colspan=\"2\"><b>".$lang_mybonus['text_message']."</b><input type=\"text\" name=\"message\" style=\"width: 400px\" maxlength=\"100\" /></td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_receiver_name']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."100</td>");
	}
	elseif ($bonusarray['art'] == 'gift_2'){  //charity giving
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\">".$lang_mybonus['text_ratio_below']."<select name=\"ratiocharity\"> <option value=\"0.1\"> 0.1</option><option value=\"0.2\"> 0.2</option><option value=\"0.3\" selected=\"selected\"> 0.3</option> <option value=\"0.4\"> 0.4</option> <option value=\"0.5\"> 0.5</option> <option value=\"0.6\"> 0.6</option><option value=\"0.7\"> 0.7</option><option value=\"0.8\"> 0.8</option></select>".$lang_mybonus['text_and_downloaded_above']." 10 GB</td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><select name=\"bonuscharity\" id=\"charityselect\" > <option value=\"1000\"> 1,000</option><option value=\"2000\"> 2,000</option><option value=\"3000\" selected=\"selected\"> 3000</option> <option value=\"5000\"> 5,000</option> <option value=\"8000\"> 8,000</option> <option value=\"10000\"> 10,000</option><option value=\"20000\"> 20,000</option><option value=\"50000\"> 50,000</option></select>".$lang_mybonus['text_karma_points']."</td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_select_receiver_ratio']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."1,000<br />".$lang_mybonus['text_max']."50,000</td>");
	}
	else {  //for VIP or Upload
		print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}

	if($CURUSER['seedbonus'] >= $bonusarray['points'])
	{
	    $permission = 'sendinvite';
		if ($bonusarray['art'] == 'gift_1'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_karma_gift']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'gift_2'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_charity_giving']."\" /></td>");
		}
		elseif($bonusarray['art'] == 'invite')
		{
			if (!\App\Support\Config\SiteConfig::current()->main->inviteSystem())
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.invite_system_closed')."\" disabled=\"disabled\" /></td>");
			elseif(!user_can($permission, false, 0)){
			$requireClass = \App\Support\Config\SiteConfig::current()->authority->permission($permission);
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.no_permission', ['class' => \App\Models\User::getClassText($requireClass)])."\" disabled=\"disabled\" /></td>");}
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif($bonusarray['art'] == 'tmp_invite')
		{
			if (!\App\Support\Config\SiteConfig::current()->main->inviteSystem())
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.invite_system_closed')."\" disabled=\"disabled\" /></td>");
			elseif(!user_can($permission, false, 0)){
			$requireClass = \App\Support\Config\SiteConfig::current()->authority->permission($permission);
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".nexus_trans('invite.send_deny_reasons.no_permission', ['class' => \App\Models\User::getClassText($requireClass)])."\" disabled=\"disabled\" /></td>");}
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'class')
		{
			if (\App\Support\UserDisplay::currentClass() >= UC_VIP)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['std_class_above_vip']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'title')
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		elseif ($bonusarray['art'] == 'traffic')
		{
			if ($CURUSER['downloaded'] > 0){
				if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824)//Uploaded amount reach limit
					$ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
				else $ratio = 0;
			}
			else $ratio = $ratiolimit_bonus + 1; //Ratio always above limit
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus){
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_ratio_too_high']."\" disabled=\"disabled\" /></td>");
			}
			else print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		} elseif ($bonusarray['art'] == 'change_username_card') {
		    if (\App\Models\UserMeta::query()->where('uid', $CURUSER['id'])->where('meta_key', \App\Models\UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_change_username_card_already_has']."\" disabled=\"disabled\"/></td>");
            } else {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
            }
        } elseif ($bonusarray['art'] == 'rainbow_id') {
            if (\App\Models\UserMeta::query()->where('uid', $CURUSER['id'])->where('meta_key', \App\Models\UserMeta::META_KEY_PERSONALIZED_USERNAME)->whereNull('deadline')->exists()) {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_rainbow_id_already_valid_forever']."\" disabled=\"disabled\"/></td>");
            } else {
                print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
            }
		} else {
            print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
        }
	}
	else
	{
		print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_more_points_needed']."\" disabled=\"disabled\" /></td>");
	}
	print("</form>");
	print("</tr>");

}

print("</table><br />");
?>

<table width="97%" cellpadding="3">
<tr><td class="colhead" align="center"><font class="big"><?php echo $lang_mybonus['text_what_is_karma'] ?></font></td></tr>
<tr><td class="text" align="left">
<?php
print("<h1>".$lang_mybonus['text_get_by_seeding']."</h1>");
print("<ul>");
if ($perseeding_bonus > 0)
	print("<li>".$perseeding_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($perseeding_bonus).$lang_mybonus['text_for_seeding_torrent'].$maxseeding_bonus.$lang_mybonus['text_torrent'].\App\Support\Strings::addS($maxseeding_bonus).")</li>");
print("<li>".$lang_mybonus['text_bonus_formula_one'].$tzero_bonus.$lang_mybonus['text_bonus_formula_two'].$nzero_bonus.$lang_mybonus['text_bonus_formula_wi'].\App\Support\Config\SiteConfig::current()->bonus->zeroBonusFactor().$lang_mybonus['text_bonus_formula_three'].$bzero_bonus.$lang_mybonus['text_bonus_formula_four'].$l_bonus.$lang_mybonus['text_bonus_formula_five']."</li>");
$minSize = \App\Support\Config\SiteConfig::current()->bonus->minSize();
if ($minSize > 0) {
    print("<li>".sprintf($lang_mybonus['text_bonus_mini_size'], \App\Support\Format::size($minSize))."</li>");
}
if ($donortimes_bonus)
	print("<li>".$lang_mybonus['text_donors_always_get'].$donortimes_bonus.$lang_mybonus['text_times_of_bonus']."</li>");

print("</ul>");

$seedBonusResult = calculate_seed_bonus($CURUSER['id']);
$A = $seedBonusResult['A'];

$bonusTableResult = \App\Support\Bonus::buildBonusTableForUser($CURUSER, $seedBonusResult, ['table_style' => 'width: 50%']);

$percent = $seedBonusResult['seed_bonus'] * 100 / ($bzero_bonus + $perseeding_bonus * $maxseeding_bonus);
print("<div align=\"center\">".$lang_mybonus['text_you_are_currently_getting'].round($seedBonusResult['seed_bonus'],3).$lang_mybonus['text_point'].\App\Support\Strings::addS($seedBonusResult['seed_bonus']).$lang_mybonus['text_per_hour']." (A = ".round($A,1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>");

if ($percent <= 30) $loadpic = "loadbarred";
elseif ($percent <= 60) $loadpic = "loadbaryellow";
else $loadpic = "loadbargreen";
$width = $percent * 4;
print("<img class=\"".$loadpic."\" src=\"pic/trans.gif\" style=\"width: ".$width."px;\" alt=\"".$percent."%\" /></td></tr></table>");

if ($bonusTableResult['has_medal_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_medal']."</h1>");
    print("<ul>");
    print("<li>".sprintf($lang_mybonus['medal_additional_desc'], $CURUSER['id'])."</li>");
    print("<li>".$lang_mybonus['medal_additional_factor'].$bonusTableResult['medal_addition_factor']."</li>");
    print("</ul>");
}
if ($bonusTableResult['has_official_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_seeding_official']."</h1>");
    print("<ul>");
    print("<li>".$lang_mybonus['official_calculate_method']."</li>");
    print("<li>".$lang_mybonus['official_tag_bonus_additional_factor'].$bonusTableResult['official_addition_factor']."</li>");
    print("</ul>");
}

if ($bonusTableResult['has_harem_addition']) {
    print("<h1>".$lang_mybonus['text_get_by_harem']."</h1>");
    print("<ul>");
    print("<li>".sprintf($lang_mybonus['harem_additional_desc'], $CURUSER['id'])."</li>");
    print("<li>".$lang_mybonus['harem_additional_factor'].$bonusTableResult['harem_addition_factor']."</li>");
    print("<li>".$lang_mybonus['harem_additional_note']."</li>");
    print("</ul>");
}

print("<h1>".$lang_mybonus['text_bonus_summary']."</h1>");
print '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$bonusTableResult['table'].'</div>';

print("<h1>".$lang_mybonus['text_other_things_get_bonus']."</h1>");
print("<ul>");
if ($uploadtorrent_bonus > 0)
	print("<li>".$lang_mybonus['text_upload_torrent'].$uploadtorrent_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($uploadtorrent_bonus)."</li>");

if ($starttopic_bonus > 0)
	print("<li>".$lang_mybonus['text_start_topic'].$starttopic_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($starttopic_bonus)."</li>");
if ($makepost_bonus > 0)
	print("<li>".$lang_mybonus['text_make_post'].$makepost_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($makepost_bonus)."</li>");
if ($addcomment_bonus > 0)
	print("<li>".$lang_mybonus['text_add_comment'].$addcomment_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($addcomment_bonus)."</li>");
if ($pollvote_bonus > 0)
	print("<li>".$lang_mybonus['text_poll_vote'].$pollvote_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($pollvote_bonus)."</li>");
if ($offervote_bonus > 0)
	print("<li>".$lang_mybonus['text_offer_vote'].$offervote_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($offervote_bonus)."</li>");
if ($saythanks_bonus > 0)
	print("<li>".$lang_mybonus['text_say_thanks'].$saythanks_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($saythanks_bonus)."</li>");
if ($receivethanks_bonus > 0)
	print("<li>".$lang_mybonus['text_receive_thanks'].$receivethanks_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($receivethanks_bonus)."</li>");
print($lang_mybonus['text_howto_get_karma_four']);
if ($ratiolimit_bonus > 0)
	print("<li>".$lang_mybonus['text_user_with_ratio_above'].$ratiolimit_bonus.$lang_mybonus['text_and_uploaded_amount_above'].$dlamountlimit_bonus.$lang_mybonus['text_cannot_exchange_uploading']."</li>");
print($lang_mybonus['text_howto_get_karma_five'].$uploadtorrent_bonus.$lang_mybonus['text_point'].\App\Support\Strings::addS($uploadtorrent_bonus).$lang_mybonus['text_howto_get_karma_six']);
?>
</td></tr></table>
<?php
}

// Bonus exchange
if ($action == "exchange") {
	if (((\App\Support\SupportContext::getPost("userid") !== null)) || ((\App\Support\SupportContext::getPost("points") !== null)) || ((\App\Support\SupportContext::getPost("bonus") !== null)) || ((\App\Support\SupportContext::getPost("art") !== null)) || !((\App\Support\SupportContext::getPost('option') !== null)) || !(isset($allBonus[\App\Support\SupportContext::getPost('option')]))){
		\App\Support\Log::writeWithContext("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to cheat at bonus system", 'mod');
		\App\Support\LegacyResponse::abort($lang_mybonus['text_error'], $lang_mybonus['text_cheat_alert'], true, false);
	}
	$option = intval(\App\Support\SupportContext::getPost("option") ?? 0);
	$bonusarray = $allBonus[$option];
	$points = $bonusarray['points'];
	$userid = $CURUSER['id'];
	$art = $bonusarray['art'];

//	$bonuscomment = $CURUSER['bonuscomment'];
	$seedbonus=$CURUSER['seedbonus']-$points;

	if($CURUSER['seedbonus'] >= $points) {
        $bonusRep = new \App\Repositories\BonusRepository();
        $lockName = "user:$userid:exchange:bonus";
        $lock = new \Nexus\Database\NexusLock($lockName, $lockSeconds);
        if (!$lock->get()) {
            do_log("[LOCKED], $lockName, $lockText");
            \App\Support\LegacyResponse::redirect('mybonus.php?do=duplicated');
        }
		//=== trade for upload
		if($art == "traffic") {
			if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824) {
                //uploaded amount reach limit
                if ($CURUSER['downloaded'] > 0) {
                    $ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
                } else {
                    $ratio = PHP_INT_MAX;
                }
            } else {
                $ratio = 0;
            }
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus)
				\App\Support\LegacyResponse::abort($lang_mybonus['text_error'], $lang_mybonus['text_cheat_alert'], true, false);
			else {
			$upload = $CURUSER['uploaded'];
			$up = $upload + $bonusarray['menge'];
            do_log(sprintf(
                "user: %s going to use %s bonus to exchange uploaded from %s to %s",
                $CURUSER['id'], $points, $CURUSER['uploaded'], $up
            ));
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for upload bonus.\n " .$bonuscomment;
//			sql_query("UPDATE users SET uploaded = ".sqlesc($up).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points. " Points for uploaded.", ['uploaded' => $up]);
			\App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=upload");
			}
		}
        if($art == "traffic_downloaded") {
            $downloaded = $CURUSER['downloaded'];
            $down = $downloaded + $bonusarray['menge'];
            do_log(sprintf(
                "user: %s going to use %s bonus to exchange downloaded from %s to %s",
                $CURUSER['id'], $points, $CURUSER['downloaded'], $down
            ));
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $points. " Points for downloaded.", ['downloaded' => $down]);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=download");
        }
		//=== trade for one month VIP status ***note "SET class = '10'" change "10" to whatever your VIP class number is
		elseif($art == "class") {
			if (\App\Support\UserDisplay::currentClass() >= UC_VIP) {
				\App\Support\Html::stdMessage($lang_mybonus['std_no_permission'], $lang_mybonus['std_class_above_vip'], 0);
				\App\Support\Html::stdfoot();
				die;
			}
			$vip_until = date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + 28*86400));
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for 1 month VIP Status.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET class = '".UC_VIP."', vip_added = 'yes', vip_until = ".sqlesc($vip_until).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP, $points. " Points for 1 month VIP Status.", ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vip_until]);
			\App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=vip");
		}
		//=== trade for invites
		elseif($art == "invite") {
			if(!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::BUY_INVITE))
				\App\Support\LegacyResponse::abort($lang_mybonus['std_sorry'], \App\Support\UserClass::name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only'], false, false);
			$invites = $CURUSER['invites'];
			$inv = $invites+$bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points. " Points for invites.", ['invites' => $inv, ]);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=invite");
		}
        //=== temporary invite
        elseif($art == "tmp_invite") {
            if(!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::BUY_INVITE))
                \App\Support\LegacyResponse::abort($lang_mybonus['std_sorry'], \App\Support\UserClass::name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only'], false, false);
//            $invites = $CURUSER['invites'];
//            $inv = $invites+$bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeToBuyTemporaryInvite($CURUSER['id']);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=tmp_invite");
        }
		//=== trade for special title
		/**** the $words array are words that you DO NOT want the user to have... use to filter "bad words" & user class...
		the user class is just for show, but what the hell tongue.gif Add more or edit to your liking.
		*note if they try to use a restricted word, they will recieve the special title "I just wasted my karma" *****/
		elseif($art == "title") {
			//===custom title
			$title = \App\Support\SupportContext::getPost("title");
			$words = array("fuck", "shit", "pussy", "cunt", "nigger", "Staff Leader","SysOp", "Administrator","Moderator","Uploader","Retiree","VIP","Nexus Master","Ultimate User","Extreme User","Veteran User","Insane User","Crazy User","Elite User","Power User","User","Peasant","Champion");
			$title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET title = ".sqlesc($title).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title.", ['title' => $title, ]);
			\App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=title");
		}
		elseif($art == 'gift_2') // charity giving
		{
			$points = intval(\App\Support\SupportContext::getPost("bonuscharity") ?? 0);
			if ($points < 1000 || $points > 50000){
				\App\Support\Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed_two'], 0);
				\App\Support\Html::stdfoot();
				die();
			}
			$ratiocharity = \App\Support\SupportContext::getPost("ratiocharity");
			if ($ratiocharity < 0.1 || $ratiocharity > 0.8){
				\App\Support\Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_ratio_not_allowed']);
				\App\Support\Html::stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".\n " .htmlspecialchars($bonuscomment);
				$charityReceiverCount = \App\Models\User::query()
				    ->where('enabled', 'yes')
				    ->whereRaw('downloaded > 10737418240')
				    ->whereRaw('? > uploaded/downloaded', [$ratiocharity])
				    ->count();
				if ($charityReceiverCount) {
//					sql_query("UPDATE users SET seedbonus = seedbonus - $points, charity = charity + $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                    $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $points. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".", ['charity' => \Nexus\Database\NexusDB::raw("charity + $points"), ]);
					$charityPerUser = $points/$charityReceiverCount;
					\App\Models\User::query()
				    ->where('enabled', 'yes')
				    ->whereRaw('downloaded > 10737418240')
				    ->whereRaw('? > uploaded/downloaded', [$ratiocharity])
				    ->increment('seedbonus', $charityPerUser);
					\App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=charity");
				}
				else
				{
					\App\Support\Html::stdMessage($lang_mybonus['std_sorry'], $lang_mybonus['std_no_users_need_charity']);
					\App\Support\Html::stdfoot();
					die;
				}
			}
		}
		elseif($art == "gift_1" && $bonusgift_bonus == 'yes') {
			//=== trade for giving the gift of karma
			$points = \App\Support\SupportContext::getPost("bonusgift");
			$message = \App\Support\SupportContext::getPost("message");
			//==gift for peeps with no more options
			$usernamegift = trim(\App\Support\SupportContext::getPost("username"));
			$receiver = \App\Models\User::query()->where('username', $usernamegift)->first(['id', 'seedbonus']);
			$arr = $receiver ? $receiver->toArray() : [];
            if (empty($arr)) {
                \App\Support\Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['text_receiver_not_exists'], 0);
                \App\Support\Html::stdfoot();
                die;
            }
			$useridgift = $arr['id'];
			$userseedbonus = $arr['seedbonus'];
//			$receiverbonuscomment = $arr['bonuscomment'];
			if (!is_numeric($points) || $points < $bonusarray['points']) {
				//write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking bonus system",'mod');
				\App\Support\Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed']);
				\App\Support\Html::stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as gift to ".htmlspecialchars(trim(\App\Support\SupportContext::getPost("username"))).".\n " .htmlspecialchars($bonuscomment);

				$aftertaxpoint = $points;
				if ($taxpercentage_bonus)
					$aftertaxpoint -= $aftertaxpoint * $taxpercentage_bonus * 0.01;
				if ($basictax_bonus)
					$aftertaxpoint -= $basictax_bonus;

				$points2receiver = number_format($aftertaxpoint,1);
//				$newreceiverbonuscomment = date("Y-m-d") . " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]).".\n " .htmlspecialchars($receiverbonuscomment);
				if ($userid==$useridgift){
					\App\Support\Html::stdMessage($lang_mybonus['text_huh'], $lang_mybonus['text_karma_self_giving_warning'], 0);
					\App\Support\Html::stdfoot();
					die;
				}

//				sql_query("UPDATE users SET seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $points2 . " Points as gift to ".htmlspecialchars(trim(\App\Support\SupportContext::getPost("username"))));
				\App\Models\User::query()->where('id', $useridgift)->increment('seedbonus', $aftertaxpoint);
                \App\Models\BonusLogs::add($useridgift, $userseedbonus, $aftertaxpoint, $userseedbonus + $aftertaxpoint, " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]), \App\Models\BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT);

				//===send message
                $locale = get_user_locale($useridgift);
				$subject = nexus_trans("bonus.msg_someone_loves_you", [], $locale);
				$msg = nexus_trans("bonus.msg_you_have_been_given", [], $locale).$points2.nexus_trans("bonus.msg_after_tax", [], $locale).$points2receiver.nexus_trans("bonus.msg_karma_points_by", [], $locale).$CURUSER['username'];
				if ($message)
				{
					$msg .= "\n".nexus_trans("bonus.msg_personal_message_from", [], $locale).$CURUSER['username'].nexus_trans("bonus.msg_colon", [], $locale).$message;
				}
				\App\Models\Message::add([
					'sender' => 0,
					'subject' => $subject,
					'added' => now(),
					'msg' => $msg,
					'receiver' => $useridgift,
				]);
				$usernamegift = unesc(\App\Support\SupportContext::getPost("username"));
                \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=transfer");
			}
			else{
				print("<table width=\"97%\"><tr><td class=\"colhead\" align=\"left\" colspan=\"2\"><h1>".$lang_mybonus['text_oups']."</h1></td></tr>");
				print("<tr><td align=\"left\"></td><td align=\"left\">".$lang_mybonus['text_not_enough_karma']."<br /><br /></td></tr></table>");
			}
		} elseif ($art == 'cancel_hr') {
		    if (empty(\App\Support\SupportContext::getPost('hr_id'))) {
		        \App\Support\LegacyResponse::abort("Error", "Invalid H&R ID: " . (\App\Support\SupportContext::getPost('hr_id') ?? ''), false, false);
            }
            $bonusRep->consumeToCancelHitAndRun($userid, \App\Support\SupportContext::getPost('hr_id'));
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=cancel_hr");
//        } elseif ($art == 'buy_medal') {
//            if (empty(\App\Support\SupportContext::getPost('medal_id'))) {

//            }
//            $bonusRep->consumeToBuyMedal($userid, \App\Support\SupportContext::getPost('medal_id'));
//            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=buy_medal");
        } elseif ($art == 'attendance_card') {
            $bonusRep->consumeToBuyAttendanceCard($userid);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=attendance_card");
        } elseif ($art == 'rainbow_id') {
            $bonusRep->consumeToBuyRainbowId($userid);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=rainbow_id");
        } elseif ($art == 'change_username_card') {
            $bonusRep->consumeToBuyChangeUsernameCard($userid);
            \App\Support\LegacyResponse::redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=change_username_card");
        }
	}
}
\App\Support\Html::stdfoot();
?>
