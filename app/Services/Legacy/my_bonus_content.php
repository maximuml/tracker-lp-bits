<?php

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Message;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Html;
use App\Support\Http;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Strings;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Nexus\Database\NexusLock;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($BASEURL)) {
    $BASEURL = SupportContext::getGlobal('BASEURL', '');
}
if (! isset($lang_mybonus)) {
    $lang_mybonus = (array) (SupportContext::getGlobal('lang_mybonus') ?? []);
}
$bonusRep = new BonusRepository;
if (! function_exists('bonusarray')) {
    function bonusarray($option = 0)
    {
        $onegbupload_bonus = SupportContext::getGlobal('onegbupload_bonus');
        $fivegbupload_bonus = SupportContext::getGlobal('fivegbupload_bonus');
        $tengbupload_bonus = SupportContext::getGlobal('tengbupload_bonus');
        $oneinvite_bonus = SupportContext::getGlobal('oneinvite_bonus');
        $customtitle_bonus = SupportContext::getGlobal('customtitle_bonus');
        $vipstatus_bonus = SupportContext::getGlobal('vipstatus_bonus');
        $basictax_bonus = SupportContext::getGlobal('basictax_bonus');
        $taxpercentage_bonus = SupportContext::getGlobal('taxpercentage_bonus');
        $lang_mybonus = (array) (SupportContext::getGlobal('lang_mybonus') ?? []);

        $results = [];
        // 1.0 GB Uploaded
        $bonus = [];
        $bonus['points'] = $onegbupload_bonus;
        $bonus['art'] = 'traffic';
        $bonus['menge'] = 1073741824;
        $bonus['name'] = $lang_mybonus['text_uploaded_one'];
        $bonus['description'] = $lang_mybonus['text_uploaded_note'];
        $results[] = $bonus;

        // 5.0 GB Uploaded
        $bonus = [];
        $bonus['points'] = $fivegbupload_bonus;
        $bonus['art'] = 'traffic';
        $bonus['menge'] = 5368709120;
        $bonus['name'] = $lang_mybonus['text_uploaded_two'];
        $bonus['description'] = $lang_mybonus['text_uploaded_note'];
        $results[] = $bonus;

        // 10.0 GB Uploaded
        $bonus = [];
        $bonus['points'] = $tengbupload_bonus;
        $bonus['art'] = 'traffic';
        $bonus['menge'] = 10737418240;
        $bonus['name'] = $lang_mybonus['text_uploaded_three'];
        $bonus['description'] = $lang_mybonus['text_uploaded_note'];
        $results[] = $bonus;

        // 100.0 GB Uploaded
        $bonus = [];
        $bonus['points'] = SiteConfig::current()->bonus->hundredGbUpload();
        $bonus['art'] = 'traffic';
        $bonus['menge'] = 107374182400;
        $bonus['name'] = $lang_mybonus['text_uploaded_four'];
        $bonus['description'] = $lang_mybonus['text_uploaded_note'];
        $results[] = $bonus;

        // 10.0 GB Downloaded
        $bonus = [];
        $bonus['points'] = SiteConfig::current()->bonus->tenGbDownload();
        $bonus['art'] = 'traffic_downloaded';
        $bonus['menge'] = 10737418240;
        $bonus['name'] = $lang_mybonus['text_downloaded_ten_gb'];
        $bonus['description'] = $lang_mybonus['text_download_note'];
        $results[] = $bonus;

        // 100.0 GB Downloaded
        $bonus = [];
        $bonus['points'] = SiteConfig::current()->bonus->hundredGbDownload();
        $bonus['art'] = 'traffic_downloaded';
        $bonus['menge'] = 107374182400;
        $bonus['name'] = $lang_mybonus['text_downloaded_hundred_gb'];
        $bonus['description'] = $lang_mybonus['text_download_note'];
        $results[] = $bonus;

        // Invite
        if ($oneinvite_bonus > 0) {
            $bonus = [];
            $bonus['points'] = $oneinvite_bonus;
            $bonus['art'] = 'invite';
            $bonus['menge'] = 1;
            $bonus['name'] = $lang_mybonus['text_buy_invite'];
            $bonus['description'] = $lang_mybonus['text_buy_invite_note'];
            $results[] = $bonus;
        }

        // Tmp Invite
        $tmpInviteBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($tmpInviteBonus > 0) {
            $bonus = [];
            $bonus['points'] = $tmpInviteBonus;
            $bonus['art'] = 'tmp_invite';
            $bonus['menge'] = 1;
            $bonus['name'] = $lang_mybonus['text_buy_tmp_invite'];
            $bonus['description'] = $lang_mybonus['text_buy_tmp_invite_note'];
            $results[] = $bonus;
        }

        // Custom Title
        $bonus = [];
        $bonus['points'] = $customtitle_bonus;
        $bonus['art'] = 'title';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_custom_title'];
        $bonus['description'] = $lang_mybonus['text_custom_title_note'];
        $results[] = $bonus;

        // VIP Status
        $bonus = [];
        $bonus['points'] = $vipstatus_bonus;
        $bonus['art'] = 'class';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_vip_status'];
        $bonus['description'] = $lang_mybonus['text_vip_status_note'];
        $results[] = $bonus;

        // Bonus Gift
        $bonus = [];
        $bonus['points'] = 100;
        $bonus['art'] = 'gift_1';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_bonus_gift'];
        $bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
        if ($basictax_bonus || $taxpercentage_bonus) {
            $onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
            $bonus['description'] .= '<br /><br />'.$lang_mybonus['text_system_charges_receiver'].'<b>'.($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].Strings::addS($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : '') : '').($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : '').'</b>'.$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
        }
        $results[] = $bonus;

        // Attendance card
        $bonus = [];
        $bonus['points'] = BonusLogs::getBonusForBuyAttendanceCard();
        $bonus['art'] = 'attendance_card';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_attendance_card'];
        $bonus['description'] = $lang_mybonus['text_attendance_card_note'];
        $results[] = $bonus;

        // Rainbow ID
        $bonus = [];
        $bonus['points'] = BonusLogs::getBonusForBuyRainbowId();
        $bonus['art'] = 'rainbow_id';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_buy_rainbow_id'];
        $bonus['description'] = $lang_mybonus['text_buy_rainbow_id_note'];
        $results[] = $bonus;

        // Change username card
        $bonus = [];
        $bonus['points'] = BonusLogs::getBonusForBuyChangeUsernameCard();
        $bonus['art'] = 'change_username_card';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_buy_change_username_card'];
        $bonus['description'] = $lang_mybonus['text_buy_change_username_card_note'];
        $results[] = $bonus;

        // Donate
        $bonus = [];
        $bonus['points'] = 1000;
        $bonus['art'] = 'gift_2';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_charity_giving'];
        $bonus['description'] = $lang_mybonus['text_charity_giving_note'];
        $results[] = $bonus;

        // Cancel hit and run
        $bonus = [];
        $bonus['points'] = BonusLogs::getBonusForCancelHitAndRun();
        $bonus['art'] = 'cancel_hr';
        $bonus['menge'] = 0;
        $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
        $bonus['description'] = '<p>
            <span style="">'.$lang_mybonus['text_cancel_hr_label'].'</span>
            <input type="number" name="hr_id" />
        </p>';
        $results[] = $bonus;

        return $results;
    }
}

$allBonus = bonusarray();
$lockSeconds = 10;
$lockText = sprintf($lang_mybonus['lock_text'], $lockSeconds);
if ($bonus_tweak == 'disable' || $bonus_tweak == 'disablesave') {
    LegacyResponse::abort($lang_mybonus['std_sorry'], $lang_mybonus['std_karma_system_disabled'].($bonus_tweak == 'disablesave' ? '<b>'.$lang_mybonus['std_points_active'].'</b>' : ''), false);
}

$action = htmlspecialchars(SupportContext::getQuery('action') ?? '');
$do = htmlspecialchars(SupportContext::getQuery('do') ?? '');
unset($msg);
if ((isset($do))) {
    if ($do == 'upload') {
        $msg = $lang_mybonus['text_success_upload'];
    } elseif ($do == 'download') {
        $msg = $lang_mybonus['text_success_download'];
    } elseif ($do == 'invite') {
        $msg = $lang_mybonus['text_success_invites'];
    } elseif ($do == 'tmp_invite') {
        $msg = $lang_mybonus['text_success_tmp_invites'];
    } elseif ($do == 'vip') {
        $msg = $lang_mybonus['text_success_vip'].'<b>'.UserClass::name(UC_VIP, false, false, true).'</b>'.$lang_mybonus['text_success_vip_two'];
    } elseif ($do == 'vipfalse') {
        $msg = $lang_mybonus['text_no_permission'];
    } elseif ($do == 'title') {
        $msg = sprintf($lang_mybonus['text_success_custom_title'], $CURUSER['title']);
    } elseif ($do == 'transfer') {
        $msg = $lang_mybonus['text_success_gift'];
    } elseif ($do == 'charity') {
        $msg = $lang_mybonus['text_success_charity'];
    } elseif ($do == 'cancel_hr') {
        $msg = $lang_mybonus['text_success_cancel_hr'];
    } elseif ($do == 'buy_medal') {
        $msg = $lang_mybonus['text_success_buy_medal'];
    } elseif ($do == 'attendance_card') {
        $msg = $lang_mybonus['text_success_buy_attendance_card'];
    } elseif ($do == 'rainbow_id') {
        $msg = $lang_mybonus['text_success_buy_rainbow_id'];
    } elseif ($do == 'change_username_card') {
        $msg = $lang_mybonus['text_success_buy_change_username_card'];
    } elseif ($do == 'duplicated') {
        $msg = $lockText;
    } else {
        $msg = '';
    }
}

$bonus = number_format($CURUSER['seedbonus'], 1);
if (! $action) {
    echo "<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n";
    echo '<tr><td class="colhead" colspan="4" align="center"><font class="big">'.$SITENAME.$lang_mybonus['text_karma_system']."</font></td></tr>\n";
    if ($msg) {
        echo '<tr><td align="center" colspan="4"><font class="striking"><b>'.$msg.'</b></font></td></tr>';
    }
    ?>
<tr><td class="text" align="center" colspan="4"><?php echo $lang_mybonus['text_exchange_your_karma']?><?php echo $bonus?><?php echo $lang_mybonus['text_for_goodies'] ?>
<br /><b><?php echo $lang_mybonus['text_no_buttons_note'] ?></b><br /><small style="color: orangered">(<?php echo $lockText ?>)</small></td></tr>
<?php

    echo '<tr><td class="colhead" align="center">'.$lang_mybonus['col_option'].'</td>'.
    '<td class="colhead" align="left">'.$lang_mybonus['col_description'].'</td>'.
    '<td class="colhead" align="center">'.$lang_mybonus['col_points'].'</td>'.
    '<td class="colhead" align="center">'.$lang_mybonus['col_trade'].'</td>'.
    '</tr>';

    for ($i = 0; $i < count($allBonus); $i++) {
        $bonusarray = $allBonus[$i];
        if (
            ($bonusarray['art'] == 'gift_1' && $bonusgift_bonus == 'no')
            || ($bonusarray['art'] == 'cancel_hr' && ! HitAndRun::getIsEnabled())
        ) {
            continue;
        }
        $bonusarrray['points'] = floatval($bonusarray['points']);

        echo '<tr>';
        echo '<form action="?action=exchange" method="post">';
        echo '<td class="rowhead_center"><input type="hidden" name="option" value="'.$i.'" /><b>'.($i + 1).'</b></td>';
        if ($bonusarray['art'] == 'title') { // for Custom Title!
            $otheroption_title = '<input type="text" name="title" style="width: 200px" maxlength="30" />';
            echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.$lang_mybonus['text_enter_titile'].$otheroption_title.$lang_mybonus['text_click_exchange']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
        } elseif ($bonusarray['art'] == 'gift_1') {  // for Give A Karma Gift
            $otheroption = '<table width="100%"><tr><td class="embedded"><b>'.$lang_mybonus['text_username'].'</b><input type="text" name="username" style="width: 200px" maxlength="24" /></td><td class="embedded"><b>'.$lang_mybonus['text_to_be_given']."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' min='100' />".$lang_mybonus['text_karma_points'].'</td></tr><tr><td class="embedded" colspan="2"><b>'.$lang_mybonus['text_message'].'</b><input type="text" name="message" style="width: 400px" maxlength="100" /></td></tr></table>';
            echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.$lang_mybonus['text_enter_receiver_name']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min'].'100</td>';
        } elseif ($bonusarray['art'] == 'gift_2') {  // charity giving
            $otheroption = '<table width="100%"><tr><td class="embedded">'.$lang_mybonus['text_ratio_below'].'<select name="ratiocharity"> <option value="0.1"> 0.1</option><option value="0.2"> 0.2</option><option value="0.3" selected="selected"> 0.3</option> <option value="0.4"> 0.4</option> <option value="0.5"> 0.5</option> <option value="0.6"> 0.6</option><option value="0.7"> 0.7</option><option value="0.8"> 0.8</option></select>'.$lang_mybonus['text_and_downloaded_above'].' 10 GB</td><td class="embedded"><b>'.$lang_mybonus['text_to_be_given'].'</b><select name="bonuscharity" id="charityselect" > <option value="1000"> 1,000</option><option value="2000"> 2,000</option><option value="3000" selected="selected"> 3000</option> <option value="5000"> 5,000</option> <option value="8000"> 8,000</option> <option value="10000"> 10,000</option><option value="20000"> 20,000</option><option value="50000"> 50,000</option></select>'.$lang_mybonus['text_karma_points'].'</td></tr></table>';
            echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.$lang_mybonus['text_select_receiver_ratio']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min'].'1,000<br />'.$lang_mybonus['text_max'].'50,000</td>';
        } else {  // for VIP or Upload
            echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
        }

        if ($CURUSER['seedbonus'] >= $bonusarray['points']) {
            $sendInvitePermission = PermissionEnum::SEND_INVITE;
            if ($bonusarray['art'] == 'gift_1') {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_karma_gift'].'" /></td>';
            } elseif ($bonusarray['art'] == 'gift_2') {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_charity_giving'].'" /></td>';
            } elseif ($bonusarray['art'] == 'invite') {
                if (! SiteConfig::current()->main->inviteSystem()) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null).'" disabled="disabled" /></td>';
                } elseif (! Permission::can(PermissionEnum::SEND_INVITE)) {
                    $requireClass = SiteConfig::current()->authority->permission($sendInvitePermission->value);
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText($requireClass)], null).'" disabled="disabled" /></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } elseif ($bonusarray['art'] == 'tmp_invite') {
                if (! SiteConfig::current()->main->inviteSystem()) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null).'" disabled="disabled" /></td>';
                } elseif (! Permission::can(PermissionEnum::SEND_INVITE)) {
                    $requireClass = SiteConfig::current()->authority->permission($sendInvitePermission->value);
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText($requireClass)], null).'" disabled="disabled" /></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } elseif ($bonusarray['art'] == 'class') {
                if (UserDisplay::currentClass() >= UC_VIP) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['std_class_above_vip'].'" disabled="disabled" /></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } elseif ($bonusarray['art'] == 'title') {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
            } elseif ($bonusarray['art'] == 'traffic') {
                if ($CURUSER['downloaded'] > 0) {
                    if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824) {// Uploaded amount reach limit
                        $ratio = $CURUSER['uploaded'] / $CURUSER['downloaded'];
                    } else {
                        $ratio = 0;
                    }
                } else {
                    $ratio = $ratiolimit_bonus + 1;
                } // Ratio always above limit
                if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['text_ratio_too_high'].'" disabled="disabled" /></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } elseif ($bonusarray['art'] == 'change_username_card') {
                if ($bonusRep->hasChangeUsernameCard((int) $CURUSER['id'])) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['text_change_username_card_already_has'].'" disabled="disabled"/></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } elseif ($bonusarray['art'] == 'rainbow_id') {
                if ($bonusRep->hasRainbowIdForever((int) $CURUSER['id'])) {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['text_rainbow_id_already_valid_forever'].'" disabled="disabled"/></td>';
                } else {
                    echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
                }
            } else {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['submit_exchange'].'" /></td>';
            }
        } else {
            echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.$lang_mybonus['text_more_points_needed'].'" disabled="disabled" /></td>';
        }
        echo '</form>';
        echo '</tr>';

    }

    echo '</table><br />';
    ?>

<table width="97%" cellpadding="3">
<tr><td class="colhead" align="center"><font class="big"><?php echo $lang_mybonus['text_what_is_karma'] ?></font></td></tr>
<tr><td class="text" align="left">
<?php
    echo '<h1>'.$lang_mybonus['text_get_by_seeding'].'</h1>';
    echo '<ul>';
    if ($perseeding_bonus > 0) {
        echo '<li>'.$perseeding_bonus.$lang_mybonus['text_point'].Strings::addS($perseeding_bonus).$lang_mybonus['text_for_seeding_torrent'].$maxseeding_bonus.$lang_mybonus['text_torrent'].Strings::addS($maxseeding_bonus).')</li>';
    }
    echo '<li>'.$lang_mybonus['text_bonus_formula_one'].$tzero_bonus.$lang_mybonus['text_bonus_formula_two'].$nzero_bonus.$lang_mybonus['text_bonus_formula_wi'].SiteConfig::current()->bonus->zeroBonusFactor().$lang_mybonus['text_bonus_formula_three'].$bzero_bonus.$lang_mybonus['text_bonus_formula_four'].$l_bonus.$lang_mybonus['text_bonus_formula_five'].'</li>';
    $minSize = SiteConfig::current()->bonus->minSize();
    if ($minSize > 0) {
        echo '<li>'.sprintf($lang_mybonus['text_bonus_mini_size'], Format::size($minSize)).'</li>';
    }
    if ($donortimes_bonus) {
        echo '<li>'.$lang_mybonus['text_donors_always_get'].$donortimes_bonus.$lang_mybonus['text_times_of_bonus'].'</li>';
    }

    echo '</ul>';

    $seedBonusResult = Bonus::calculateForUser($CURUSER['id'], null);
    $A = $seedBonusResult['A'];

    $bonusTableResult = Bonus::buildBonusTableForUser($CURUSER, $seedBonusResult, ['table_style' => 'width: 50%']);

    $percent = $seedBonusResult['seed_bonus'] * 100 / ($bzero_bonus + $perseeding_bonus * $maxseeding_bonus);
    echo '<div align="center">'.$lang_mybonus['text_you_are_currently_getting'].round($seedBonusResult['seed_bonus'], 3).$lang_mybonus['text_point'].Strings::addS($seedBonusResult['seed_bonus']).$lang_mybonus['text_per_hour'].' (A = '.round($A, 1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>";

    if ($percent <= 30) {
        $loadpic = 'loadbarred';
    } elseif ($percent <= 60) {
        $loadpic = 'loadbaryellow';
    } else {
        $loadpic = 'loadbargreen';
    }
    $width = $percent * 4;
    echo '<img class="'.$loadpic.'" src="pic/trans.gif" style="width: '.$width.'px;" alt="'.$percent.'%" /></td></tr></table>';

    if ($bonusTableResult['has_medal_addition']) {
        echo '<h1>'.$lang_mybonus['text_get_by_medal'].'</h1>';
        echo '<ul>';
        echo '<li>'.sprintf($lang_mybonus['medal_additional_desc'], $CURUSER['id']).'</li>';
        echo '<li>'.$lang_mybonus['medal_additional_factor'].$bonusTableResult['medal_addition_factor'].'</li>';
        echo '</ul>';
    }
    if ($bonusTableResult['has_official_addition']) {
        echo '<h1>'.$lang_mybonus['text_get_by_seeding_official'].'</h1>';
        echo '<ul>';
        echo '<li>'.$lang_mybonus['official_calculate_method'].'</li>';
        echo '<li>'.$lang_mybonus['official_tag_bonus_additional_factor'].$bonusTableResult['official_addition_factor'].'</li>';
        echo '</ul>';
    }

    if ($bonusTableResult['has_harem_addition']) {
        echo '<h1>'.$lang_mybonus['text_get_by_harem'].'</h1>';
        echo '<ul>';
        echo '<li>'.sprintf($lang_mybonus['harem_additional_desc'], $CURUSER['id']).'</li>';
        echo '<li>'.$lang_mybonus['harem_additional_factor'].$bonusTableResult['harem_addition_factor'].'</li>';
        echo '<li>'.$lang_mybonus['harem_additional_note'].'</li>';
        echo '</ul>';
    }

    echo '<h1>'.$lang_mybonus['text_bonus_summary'].'</h1>';
    echo '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$bonusTableResult['table'].'</div>';

    echo '<h1>'.$lang_mybonus['text_other_things_get_bonus'].'</h1>';
    echo '<ul>';
    if ($uploadtorrent_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_upload_torrent'].$uploadtorrent_bonus.$lang_mybonus['text_point'].Strings::addS($uploadtorrent_bonus).'</li>';
    }

    if ($starttopic_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_start_topic'].$starttopic_bonus.$lang_mybonus['text_point'].Strings::addS($starttopic_bonus).'</li>';
    }
    if ($makepost_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_make_post'].$makepost_bonus.$lang_mybonus['text_point'].Strings::addS($makepost_bonus).'</li>';
    }
    if ($addcomment_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_add_comment'].$addcomment_bonus.$lang_mybonus['text_point'].Strings::addS($addcomment_bonus).'</li>';
    }
    if ($pollvote_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_poll_vote'].$pollvote_bonus.$lang_mybonus['text_point'].Strings::addS($pollvote_bonus).'</li>';
    }
    if ($offervote_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_offer_vote'].$offervote_bonus.$lang_mybonus['text_point'].Strings::addS($offervote_bonus).'</li>';
    }
    if ($saythanks_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_say_thanks'].$saythanks_bonus.$lang_mybonus['text_point'].Strings::addS($saythanks_bonus).'</li>';
    }
    if ($receivethanks_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_receive_thanks'].$receivethanks_bonus.$lang_mybonus['text_point'].Strings::addS($receivethanks_bonus).'</li>';
    }
    echo $lang_mybonus['text_howto_get_karma_four'];
    if ($ratiolimit_bonus > 0) {
        echo '<li>'.$lang_mybonus['text_user_with_ratio_above'].$ratiolimit_bonus.$lang_mybonus['text_and_uploaded_amount_above'].$dlamountlimit_bonus.$lang_mybonus['text_cannot_exchange_uploading'].'</li>';
    }
    echo $lang_mybonus['text_howto_get_karma_five'].$uploadtorrent_bonus.$lang_mybonus['text_point'].Strings::addS($uploadtorrent_bonus).$lang_mybonus['text_howto_get_karma_six'];
    ?>
</td></tr></table>
<?php
}

// Bonus exchange
if ($action == 'exchange') {
    if (((SupportContext::getPost('userid') !== null)) || ((SupportContext::getPost('points') !== null)) || ((SupportContext::getPost('bonus') !== null)) || ((SupportContext::getPost('art') !== null)) || ! ((SupportContext::getPost('option') !== null)) || ! (isset($allBonus[SupportContext::getPost('option')]))) {
        Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is trying to cheat at bonus system', 'mod');
        LegacyResponse::abort($lang_mybonus['text_error'], $lang_mybonus['text_cheat_alert'], true, false);
    }
    $option = intval(SupportContext::getPost('option') ?? 0);
    $bonusarray = $allBonus[$option];
    $points = $bonusarray['points'];
    $userid = $CURUSER['id'];
    $art = $bonusarray['art'];

    //	$bonuscomment = $CURUSER['bonuscomment'];
    $seedbonus = $CURUSER['seedbonus'] - $points;

    if ($CURUSER['seedbonus'] >= $points) {
        $lockName = "user:$userid:exchange:bonus";
        $lock = new NexusLock($lockName, $lockSeconds);
        if (! $lock->get()) {
            Logger::writeWithContext((string) "[LOCKED], {$lockName}, {$lockText}", (string) 'info', (bool) false);
            LegacyResponse::redirect('mybonus.php?do=duplicated');
        }
        // === trade for upload
        if ($art == 'traffic') {
            if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824) {
                // uploaded amount reach limit
                if ($CURUSER['downloaded'] > 0) {
                    $ratio = $CURUSER['uploaded'] / $CURUSER['downloaded'];
                } else {
                    $ratio = PHP_INT_MAX;
                }
            } else {
                $ratio = 0;
            }
            if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus) {
                LegacyResponse::abort($lang_mybonus['text_error'], $lang_mybonus['text_cheat_alert'], true, false);
            } else {
                $upload = $CURUSER['uploaded'];
                $up = $upload + $bonusarray['menge'];
                Logger::writeWithContext((string) sprintf('user: %s going to use %s bonus to exchange uploaded from %s to %s', $CURUSER['id'], $points, $CURUSER['uploaded'], $up), (string) 'info', (bool) false);
                //			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for upload bonus.\n " .$bonuscomment;
                //			sql_query("UPDATE users SET uploaded = ".sqlesc($up).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points.' Points for uploaded.', ['uploaded' => $up]);
                LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=upload");
            }
        }
        if ($art == 'traffic_downloaded') {
            $downloaded = $CURUSER['downloaded'];
            $down = $downloaded + $bonusarray['menge'];
            Logger::writeWithContext((string) sprintf('user: %s going to use %s bonus to exchange downloaded from %s to %s', $CURUSER['id'], $points, $CURUSER['downloaded'], $down), (string) 'info', (bool) false);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $points.' Points for downloaded.', ['downloaded' => $down]);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=download");
        }
        // === trade for one month VIP status ***note "SET class = '10'" change "10" to whatever your VIP class number is
        elseif ($art == 'class') {
            if (UserDisplay::currentClass() >= UC_VIP) {
                Html::stdMessage($lang_mybonus['std_no_permission'], $lang_mybonus['std_class_above_vip'], 0);

                return;
            }
            $vip_until = date('Y-m-d H:i:s', (strtotime(date('Y-m-d H:i:s')) + 28 * 86400));
            //			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for 1 month VIP Status.\n " .htmlspecialchars($bonuscomment);
            //			sql_query("UPDATE users SET class = '".UC_VIP."', vip_added = 'yes', vip_until = ".sqlesc($vip_until).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_BUY_VIP, $points.' Points for 1 month VIP Status.', ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vip_until]);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=vip");
        }
        // === trade for invites
        elseif ($art == 'invite') {
            if (! Permission::can(PermissionEnum::BUY_INVITE)) {
                LegacyResponse::abort($lang_mybonus['std_sorry'], UserClass::name($buyinvite_class, false, false, true).$lang_mybonus['text_plus_only'], false, false);
            }
            $invites = $CURUSER['invites'];
            $inv = $invites + $bonusarray['menge'];
            //			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
            //			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points.' Points for invites.', ['invites' => $inv]);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=invite");
        }
        // === temporary invite
        elseif ($art == 'tmp_invite') {
            if (! Permission::can(PermissionEnum::BUY_INVITE)) {
                LegacyResponse::abort($lang_mybonus['std_sorry'], UserClass::name($buyinvite_class, false, false, true).$lang_mybonus['text_plus_only'], false, false);
            }
            //            $invites = $CURUSER['invites'];
            //            $inv = $invites+$bonusarray['menge'];
            //			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
            //			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeToBuyTemporaryInvite($CURUSER['id']);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=tmp_invite");
        }
        // === trade for special title
        /**** the $words array are words that you DO NOT want the user to have... use to filter "bad words" & user class...
        the user class is just for show, but what the hell tongue.gif Add more or edit to your liking.
        *note if they try to use a restricted word, they will recieve the special title "I just wasted my karma" *****/
        elseif ($art == 'title') {
            // ===custom title
            $title = SupportContext::getPost('title');
            $words = ['fuck', 'shit', 'pussy', 'cunt', 'nigger', 'Staff Leader', 'SysOp', 'Administrator', 'Moderator', 'Uploader', 'Retiree', 'VIP', 'Nexus Master', 'Ultimate User', 'Extreme User', 'Veteran User', 'Insane User', 'Crazy User', 'Elite User', 'Power User', 'User', 'Peasant', 'Champion'];
            $title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
            //			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title\n " .htmlspecialchars($bonuscomment);
            //			sql_query("UPDATE users SET title = ".sqlesc($title).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points.' Points for custom title. Old title is '.htmlspecialchars(trim($CURUSER['title']))." and new title is $title.", ['title' => $title]);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=title");
        } elseif ($art == 'gift_2') { // charity giving
            $points = intval(SupportContext::getPost('bonuscharity') ?? 0);
            if ($points < 1000 || $points > 50000) {
                Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed_two'], 0);

                return;
            }
            $ratiocharity = SupportContext::getPost('ratiocharity');
            if ($ratiocharity < 0.1 || $ratiocharity > 0.8) {
                Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_ratio_not_allowed']);

                return;
            }
            if ($CURUSER['seedbonus'] >= $points) {
                $points2 = number_format($points, 1);
                //				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".\n " .htmlspecialchars($bonuscomment);
                $charityReceiverCount = $bonusRep->getCharityReceiverCount((float) $ratiocharity);
                if ($charityReceiverCount) {
                    //					sql_query("UPDATE users SET seedbonus = seedbonus - $points, charity = charity + $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                    $bonusRep->consumeUserBonusAndIncrementCharity($CURUSER['id'], (float) $points, BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $points.' Points as charity to users with ratio below '.htmlspecialchars(trim($ratiocharity)).'.', (float) $points);
                    $charityPerUser = $points / $charityReceiverCount;
                    $bonusRep->incrementSeedbonusForLowRatioReceivers((float) $ratiocharity, (float) $charityPerUser);
                    LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=charity");
                } else {
                    Html::stdMessage($lang_mybonus['std_sorry'], $lang_mybonus['std_no_users_need_charity']);

                    return;
                }
            }
        } elseif ($art == 'gift_1' && $bonusgift_bonus == 'yes') {
            // === trade for giving the gift of karma
            $points = SupportContext::getPost('bonusgift');
            $message = SupportContext::getPost('message');
            // ==gift for peeps with no more options
            $usernamegift = trim(SupportContext::getPost('username'));
            $arr = $bonusRep->findGiftReceiver($usernamegift);
            if (empty($arr)) {
                Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['text_receiver_not_exists'], 0);

                return;
            }
            $useridgift = $arr['id'];
            $userseedbonus = $arr['seedbonus'];
            //			$receiverbonuscomment = $arr['bonuscomment'];
            if (! is_numeric($points) || $points < $bonusarray['points']) {
                // write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking bonus system",'mod');
                Html::stdMessage($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed']);

                return;
            }
            if ($CURUSER['seedbonus'] >= $points) {
                $points2 = number_format($points, 1);
                //				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as gift to ".htmlspecialchars(trim(\App\Support\SupportContext::getPost("username"))).".\n " .htmlspecialchars($bonuscomment);

                $aftertaxpoint = $points;
                if ($taxpercentage_bonus) {
                    $aftertaxpoint -= $aftertaxpoint * $taxpercentage_bonus * 0.01;
                }
                if ($basictax_bonus) {
                    $aftertaxpoint -= $basictax_bonus;
                }

                $points2receiver = number_format($aftertaxpoint, 1);
                //				$newreceiverbonuscomment = date("Y-m-d") . " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]).".\n " .htmlspecialchars($receiverbonuscomment);
                if ($userid == $useridgift) {
                    Html::stdMessage($lang_mybonus['text_huh'], $lang_mybonus['text_karma_self_giving_warning'], 0);

                    return;
                }

                //				sql_query("UPDATE users SET seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $points2.' Points as gift to '.htmlspecialchars(trim(SupportContext::getPost('username'))));
                $bonusRep->incrementUserSeedbonus((int) $useridgift, (float) $aftertaxpoint);
                BonusLogs::add($useridgift, $userseedbonus, $aftertaxpoint, $userseedbonus + $aftertaxpoint, ' + '.$points2receiver.' Points (after tax) as a gift from '.($CURUSER['username']), BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT);

                // ===send message
                $locale = Locale::userLocale($useridgift);
                $subject = Locale::trans('bonus.msg_someone_loves_you', [], $locale);
                $msg = Locale::trans('bonus.msg_you_have_been_given', [], $locale).$points2.Locale::trans('bonus.msg_after_tax', [], $locale).$points2receiver.Locale::trans('bonus.msg_karma_points_by', [], $locale).$CURUSER['username'];
                if ($message) {
                    $msg .= "\n".Locale::trans('bonus.msg_personal_message_from', [], $locale).$CURUSER['username'].Locale::trans('bonus.msg_colon', [], $locale).$message;
                }
                Message::add([
                    'sender' => 0,
                    'subject' => $subject,
                    'added' => now(),
                    'msg' => $msg,
                    'receiver' => $useridgift,
                ]);
                $usernamegift = Input::unescape(SupportContext::getPost('username'));
                LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=transfer");
            } else {
                echo '<table width="97%"><tr><td class="colhead" align="left" colspan="2"><h1>'.$lang_mybonus['text_oups'].'</h1></td></tr>';
                echo '<tr><td align="left"></td><td align="left">'.$lang_mybonus['text_not_enough_karma'].'<br /><br /></td></tr></table>';
            }
        } elseif ($art == 'cancel_hr') {
            if (empty(SupportContext::getPost('hr_id'))) {
                LegacyResponse::abort('Error', 'Invalid H&R ID: '.(SupportContext::getPost('hr_id') ?? ''), false, false);
            }
            $bonusRep->consumeToCancelHitAndRun($userid, SupportContext::getPost('hr_id'));
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=cancel_hr");
            //        } elseif ($art == 'buy_medal') {
            //            if (empty(\App\Support\SupportContext::getPost('medal_id'))) {

            //            }
            //            $bonusRep->consumeToBuyMedal($userid, \App\Support\SupportContext::getPost('medal_id'));
            //            nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=buy_medal");
        } elseif ($art == 'attendance_card') {
            $bonusRep->consumeToBuyAttendanceCard($userid);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=attendance_card");
        } elseif ($art == 'rainbow_id') {
            $bonusRep->consumeToBuyRainbowId($userid);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=rainbow_id");
        } elseif ($art == 'change_username_card') {
            $bonusRep->consumeToBuyChangeUsernameCard($userid);
            LegacyResponse::redirect(''.Http::protocolPrefix(Url::isSecure())."$BASEURL/mybonus.php?do=change_username_card");
        }
    }
}
?>