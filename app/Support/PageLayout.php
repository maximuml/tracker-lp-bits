<?php

namespace App\Support;

use App\Models\SearchBox;
class PageLayout
{
    public static function header($title = "", $msgalert = true, $script = "", $place = "")
    {
        global $lang_functions;
        global $CURUSER, $CURLANGDIR, $USERUPDATESET, $iplog1, $oldip, $SITE_ONLINE, $FUNDS, $SITENAME, $SLOGAN, $logo_main, $BASEURL, $offlinemsg, $enabledonation, $staffmem_class, $titlekeywords_tweak, $metakeywords_tweak, $metadescription_tweak, $cssdate_tweak, $deletenotransfertwo_account, $neverdelete_account, $iniupload_main;
        global $tstart;
        global $Cache;
        $Cache->setLanguage($CURLANGDIR);
        $cssupdatedate = $cssdate_tweak;
        // Variable for Start Time
        $tstart = getmicrotime();
        // Start time
        //Insert old ip into iplog
        if ($CURUSER) {
            //		if ($iplog1 == "yes") {
            //			if (($oldip != $CURUSER["ip"]) && $CURUSER["ip"])
            //			sql_query("INSERT INTO iplog (ip, userid, access) VALUES (" . sqlesc($CURUSER['ip']) . ", " . $CURUSER['id'] . ", '" . $CURUSER['last_access'] . "')");
            //		}
            //record always
            \App\Repositories\IpLogRepository::saveToCache($CURUSER['id']);
            $USERUPDATESET[] = "last_access = " . \App\Support\LegacyDb::escape(date("Y-m-d H:i:s"));
            $USERUPDATESET[] = "ip = " . \App\Support\LegacyDb::escape($CURUSER['ip']);
        }
        header("Content-Type: text/html; charset=utf-8; Cache-control:private");
        //header("Pragma: No-cache");
        if ($title == "") {
            $title = $SITENAME;
        } else {
            $title = $SITENAME . " :: " . htmlspecialchars($title);
        }
        if ($titlekeywords_tweak) {
            $title .= " " . htmlspecialchars($titlekeywords_tweak);
        }
        $title .= " - Powered by " . PROJECTNAME;
        if ($SITE_ONLINE == "no") {
            if (get_user_class() < UC_ADMINISTRATOR) {
                die($lang_functions['std_site_down_for_maintenance']);
            } else {
                $offlinemsg = true;
            }
        }
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php 
        if ($metakeywords_tweak) {
            ?>
<meta name="keywords" content="<?php 
            echo htmlspecialchars($metakeywords_tweak);
            ?>" />
<?php 
        }
        if ($metadescription_tweak) {
            ?>
<meta name="description" content="<?php 
            echo htmlspecialchars($metadescription_tweak);
            ?>" />
<?php 
        }
        ?>
<meta name="generator" content="<?php 
        echo PROJECTNAME;
        ?>" />
<?php 
        print get_style_addicode();
        $css_uri = get_css_uri();
        $cssupdatedate = $cssupdatedate ? "?" . htmlspecialchars($cssupdatedate) : "";
        ?>
<title><?php 
        echo $title;
        ?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="<?php 
        echo $SITENAME;
        ?> Torrents" href="opensearch.php" />
<link rel="stylesheet" href="<?php 
        echo get_font_css_uri() . $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css<?php 
        echo $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php 
        echo get_forum_pic_folder() . "/forumsprites.css" . $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php 
        echo $css_uri . "theme.css" . $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php 
        echo $css_uri . "DomTT.css" . $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css<?php 
        echo $cssupdatedate;
        ?>" type="text/css" />
<?php 
        if ($CURUSER) {
            //	$caticonrow = get_category_icon_row($CURUSER['caticon']);
            //	if($caticonrow['cssfile']){
            $requireSearchBoxIdAr = list_require_search_box_id();
            if (!empty($requireSearchBoxIdAr)) {
                $icons = (new \App\Repositories\SearchBoxRepository())->listIcon($requireSearchBoxIdAr);
                foreach ($icons as $icon) {
                    ?>
<link rel="stylesheet" href="<?php 
                    echo htmlspecialchars(trim($icon['cssfile'] ?? '', '/')) . $cssupdatedate;
                    ?>" type="text/css" />
<?php 
                }
            }
        }
        ?>
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<script type="text/javascript" src="js/curtain_imageresizer.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/ajaxbasic.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/common.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/domLib.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/domTT.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/domTT_drag.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript" src="js/fadomatic.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<?php 
        do_action('nexus_header');
        foreach (\Nexus\Nexus::getAppendHeaders() as $value) {
            print $value;
        }
        ?>
<script type="text/javascript" src="js/jquery-1.12.4.min.js<?php 
        echo $cssupdatedate;
        ?>"></script>
<script type="text/javascript">
    jQuery.noConflict();
    window.nexusLayerOptions = {
        confirm: {btnAlign: 'c', title: 'Confirm', btn: ['OK', 'Cancel']},
        alert: {btnAlign: 'c', title: 'Info', btn: ['OK', 'Cancel']}
    }
</script>
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js<?php 
        echo $cssupdatedate;
        ?>"></script>
</head>
<body>
<table class="head" cellspacing="0" cellpadding="0" align="center" style="width: <?php 
        echo isset($GLOBALS['CURUSER']) ? CONTENT_WIDTH + 28.66 : CONTENT_WIDTH;
        ?>px">
	<tr>
		<td class="clear">
<?php 
        if ($logo_main == "") {
            ?>
			<div class="logo"><?php 
            echo htmlspecialchars($SITENAME);
            ?></div>
			<div class="slogan"><?php 
            echo htmlspecialchars($SLOGAN);
            ?></div>
<?php 
        } else {
            ?>
			<div class="logo_img"><img src="<?php 
            echo $logo_main;
            ?>" alt="<?php 
            echo htmlspecialchars($SITENAME);
            ?>" title="<?php 
            echo htmlspecialchars($SITENAME);
            ?> - <?php 
            echo htmlspecialchars($SLOGAN);
            ?>" /></div>
<?php 
        }
        ?>
		</td>
		<td class="clear nowrap" align="right" valign="middle">
<?php 
        if ($enabledonation == 'yes') {
            ?>
			<a href="donate.php"><img src="<?php 
            echo get_forum_pic_folder();
            ?>/donate.gif" alt="Make a donation" style="margin-left: 5px; margin-top: 50px;" /></a>
<?php 
        }
        ?>
		</td>
	</tr>
</table>

<table class="mainouter" width="<?php 
        echo CONTENT_WIDTH;
        ?>" cellspacing="0" cellpadding="5" align="center">
	<tr><td id="nav_block" class="text" align="center">
<?php 
        if (!$CURUSER) {
            ?>
			<a href="login.php"><font class="big"><b><?php 
            echo $lang_functions['text_login'];
            ?></b></font></a> / <a href="signup.php"><font class="big"><b><?php 
            echo $lang_functions['text_signup'];
            ?></b></font></a>
<?php 
        } else {
            begin_main_frame();
            menu();
            end_main_frame();
            $datum = getdate();
            $datum["hours"] = sprintf("%02.0f", $datum["hours"]);
            $datum["minutes"] = sprintf("%02.0f", $datum["minutes"]);
            $ratio = get_ratio($CURUSER['id']);
            //// check every 15 minutes //////////////////
            $messages = $Cache->get_value('user_' . $CURUSER["id"] . '_inbox_count');
            if ($messages == "") {
                $messages = get_row_count("messages", "WHERE receiver=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " AND location<>0");
                $Cache->cache_value('user_' . $CURUSER["id"] . '_inbox_count', $messages, 900);
            }
            $outmessages = $Cache->get_value('user_' . $CURUSER["id"] . '_outbox_count');
            if ($outmessages == "") {
                $outmessages = get_row_count("messages", "WHERE sender=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " AND saved='yes'");
                $Cache->cache_value('user_' . $CURUSER["id"] . '_outbox_count', $outmessages, 900);
            }
            if (!$connect = $Cache->get_value('user_' . $CURUSER["id"] . '_connect')) {
                $res3 = \Nexus\Database\NexusDB::select("SELECT connectable FROM peers WHERE userid=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " order by id desc LIMIT 1");
                if ($row = mysql_fetch_row($res3)) {
                    $connect = $row[0];
                } else {
                    $connect = 'unknown';
                }
                $Cache->cache_value('user_' . $CURUSER["id"] . '_connect', $connect, 900);
            }
            if ($connect == "yes") {
                $connectable = "<b><font color=\"green\">" . $lang_functions['text_yes'] . "</font></b>";
            } elseif ($connect == 'no') {
                $connectable = "<a href=\"faq.php#id21\"><b><font color=\"red\">" . $lang_functions['text_no'] . "</font></b></a>";
            } else {
                $connectable = $lang_functions['text_unknown'];
            }
            //// check every 60 seconds //////////////////
            $activeseed = $Cache->get_value('user_' . $CURUSER["id"] . '_active_seed_count');
            if ($activeseed == "") {
                $activeseed = get_row_count("peers", "WHERE userid=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " AND seeder='yes'");
                $Cache->cache_value('user_' . $CURUSER["id"] . '_active_seed_count', $activeseed, 60);
            }
            $activeleech = $Cache->get_value('user_' . $CURUSER["id"] . '_active_leech_count');
            if ($activeleech == "") {
                $activeleech = get_row_count("peers", "WHERE userid=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " AND seeder='no'");
                $Cache->cache_value('user_' . $CURUSER["id"] . '_active_leech_count', $activeleech, 60);
            }
            $unread = $Cache->get_value('user_' . $CURUSER["id"] . '_unread_message_count');
            if ($unread == "") {
                $unread = get_row_count("messages", "WHERE receiver=" . \App\Support\LegacyDb::escape($CURUSER["id"]) . " AND unread='yes'");
                $Cache->cache_value('user_' . $CURUSER["id"] . '_unread_message_count', $unread, 60);
            }
            $inboxpic = "<img class=\"" . ($unread ? "inboxnew" : "inbox") . "\" src=\"pic/trans.gif\" alt=\"inbox\" title=\"" . ($unread ? $lang_functions['title_inbox_new_messages'] : $lang_functions['title_inbox_no_new_messages']) . "\" />";
            //    $attend_desk = new Attendance($CURUSER['id']);
            //    $attendance = $attend_desk->check();
            $attendanceRep = new \App\Repositories\AttendanceRepository();
            $attendance = $attendanceRep->getAttendance($CURUSER['id'], date('Ymd'));
            ?>

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left">
            <span class="medium">
                <?php 
            echo $lang_functions['text_welcome_back'];
            ?>, <?php 
            echo get_username($CURUSER['id']);
            ?>
                [<a href="logout.php"><?php 
            echo $lang_functions['text_logout'];
            ?></a>]
                [<a href="usercp.php"><?php 
            echo $lang_functions['text_user_cp'];
            ?></a>]
                <?php 
            if (get_user_class() >= UC_MODERATOR) {
                ?> [<a href="staffpanel.php"><?php 
                echo $lang_functions['text_staff_panel'];
                ?></a>] <?php 
            }
            ?>
                <?php 
            if (get_user_class() >= UC_SYSOP) {
                ?> [<a href="settings.php"><?php 
                echo $lang_functions['text_site_settings'];
                ?></a>]<?php 
            }
            ?>
                [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0"><?php 
            echo $lang_functions['text_bookmarks'];
            ?></a>]
                <font class = 'color_bonus'><?php 
            echo $lang_functions['text_bonus'];
            ?></font>[<a href="mybonus.php"><?php 
            echo $lang_functions['text_use'];
            ?></a>]: <?php 
            echo number_format($CURUSER['seedbonus'], 1);
            ?>
                <?php 
            if ($attendance) {
                printf(' <a href="attendance.php" class="">' . $lang_functions['text_attended'] . '</a>', $attendance->points, $CURUSER['attendance_card']);
            } else {
                printf(' <a href="attendance.php" class="faqlink">%s</a>', $lang_functions['text_attendance']);
            }
            ?>
                <a href="medal.php">[<?php 
            echo nexus_trans('medal.label');
            ?>]</a>
                <a href="task.php">[<?php 
            echo nexus_trans('exam.type_task');
            ?>]</a>
                <font class = 'color_invite'><?php 
            echo $lang_functions['text_invite'];
            ?></font>[<a href="invite.php?id=<?php 
            echo $CURUSER['id'];
            ?>"><?php 
            echo $lang_functions['text_send'];
            ?></a>]: <?php 
            echo sprintf('%s(%s)', $CURUSER['invites'], \App\Models\Invite::query()->where('inviter', $CURUSER['id'])->where('invitee', '')->where('expired_at', '>', now())->count());
            ?>
                <?php 
            if (get_user_class() >= \App\Models\User::getAccessAdminClassMin()) {
                printf('[<a href="%s" target="_blank">%s</a>]', nexus_env('FILAMENT_PATH', 'nexusphp'), $lang_functions['text_management_system']);
            }
            ?>
                <br />
	            <font class="color_ratio"><?php 
            echo $lang_functions['text_ratio'];
            ?></font> <?php 
            echo $ratio;
            ?>
                <font class='color_uploaded'><?php 
            echo $lang_functions['text_uploaded'];
            ?></font> <?php 
            echo mksize($CURUSER['uploaded']);
            ?>
                <font class='color_downloaded'> <?php 
            echo $lang_functions['text_downloaded'];
            ?></font> <?php 
            echo mksize($CURUSER['downloaded']);
            ?>
                <font class='color_active'><?php 
            echo $lang_functions['text_active_torrents'];
            ?></font> <img class="arrowup" alt="Torrents seeding" title="<?php 
            echo $lang_functions['title_torrents_seeding'];
            ?>" src="pic/trans.gif" /><?php 
            echo $activeseed;
            ?>  <img class="arrowdown" alt="Torrents leeching" title="<?php 
            echo $lang_functions['title_torrents_leeching'];
            ?>" src="pic/trans.gif" /><?php 
            echo $activeleech;
            ?>&nbsp;&nbsp;
                <font class='color_connectable'><?php 
            echo $lang_functions['text_connectable'];
            ?></font><?php 
            echo $connectable;
            ?> <?php 
            echo maxslots();
            ?>
                <?php 
            if (\App\Models\HitAndRun::getIsEnabled()) {
                ?><font class='color_bonus'>H&R: </font> <?php 
                echo sprintf('[<a href="myhr.php">%s</a>]', (new \App\Repositories\HitAndRunRepository())->getStatusStats($CURUSER['id']));
            }
            ?>
            </span>
        </td>
                <?php 
            if (SearchBox::isSpecialEnabled() && get_setting('main.enable_global_search') == 'yes') {
                ?>
        <td class="bottom" align="left" style="border: none">
            <form action="search.php" method="get" target="<?php 
                echo nexus()->getScript() == 'search' ? '_self' : '_blank';
                ?>">
                <div style="display: flex;align-items: center">
                    <div style="display: flex;flex-direction: column">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="<?php 
                echo $_GET['search'] ?? '';
                ?>" placeholder="<?php 
                echo nexus_trans('search.search_keyword');
                ?>"/></span>
                        </div>
                        <div>
                            <span><?php 
                echo build_search_area($_GET['search_area'] ?? '', ['style' => 'width: 88px']);
                ?></span>
                        </div>
                    </div>
                    <div><input type="submit" value="<?php 
                echo nexus_trans('search.global_search');
                ?>" style="width: 39px;white-space: break-spaces;padding: 0" /></div>
                </div>
            </form>
        </td>
                <?php 
            }
            ?>
	<td class="bottom" align="right"><span class="medium">
<?php 
            if (user_can('staffmem')) {
                $totalreports = $Cache->get_value('staff_report_count');
                if ($totalreports == "") {
                    $totalreports = get_row_count("reports");
                    $Cache->cache_value('staff_report_count', $totalreports, 900);
                }
                $totalcheaters = $Cache->get_value('staff_cheater_count');
                if ($totalcheaters == "") {
                    $totalcheaters = get_row_count("cheaters");
                    $Cache->cache_value('staff_cheater_count', $totalcheaters, 900);
                }
                print "<a href=\"cheaterbox.php\"><img class=\"cheaterbox\" alt=\"cheaterbox\" title=\"" . $lang_functions['title_cheaterbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalcheaters . "  <a href=\"reports.php\"><img class=\"reportbox\" alt=\"reportbox\" title=\"" . $lang_functions['title_reportbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalreports;
            }
            print " <a href=\"friends.php\"><img class=\"buddylist\" alt=\"Buddylist\" title=\"" . $lang_functions['title_buddylist'] . "\" src=\"pic/trans.gif\" /></a>";
            print " <a href=\"getrss.php\"><img class=\"rss\" alt=\"RSS\" title=\"" . $lang_functions['title_get_rss'] . "\" src=\"pic/trans.gif\" /></a>";
            print '<br/>';
            //echo $lang_functions['text_the_time_is_now'].$datum['hours'].":".$datum['minutes'] . '<br />';
            //	$cacheKey = "staff_message_count_" . $CURUSER['id'];
            //    $totalsm = $Cache->get_value($cacheKey);
            $totalsm = \App\Repositories\MessageRepository::getStaffMessageCountCache($CURUSER['id'], 'total');
            if ($totalsm === false) {
                $totalsm = \App\Repositories\MessageRepository::countStaffMessage($CURUSER['id']);
                //        $Cache->cache_value($cacheKey, $totalsm, 900);
                \App\Repositories\MessageRepository::updateStaffMessageCountCache($CURUSER['id'], 'total', $totalsm);
            }
            if ($totalsm > 0) {
                print "  <a href=\"staffbox.php\"><img class=\"staffbox\" alt=\"staffbox\" title=\"" . $lang_functions['title_staffbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalsm . "  ";
            }
            print "<a href=\"messages.php\">" . $inboxpic . "</a> " . ($messages ? $messages . " (" . $unread . $lang_functions['text_message_new'] . ")" : "0");
            print "  <a href=\"messages.php?action=viewmailbox&amp;box=-1\"><img class=\"sentbox\" alt=\"sentbox\" title=\"" . $lang_functions['title_sentbox'] . "\" src=\"pic/trans.gif\" /></a> " . ($outmessages ? $outmessages : "0");
            ?>

	</span></td>
	</tr></table></td>
</tr></table>

</td></tr>

<tr><td id="outer" align="center" class="outer" style="padding-top: 20px; padding-bottom: 20px">
<?php 
            if ($msgalert) {
                $timeline = \App\Models\TorrentState::resolveTimeline();
                $currentPromotion = $timeline['current'] ?? null;
                $upcomingPromotion = $timeline['upcoming'] ?? null;
                $remarkTpl = $lang_functions['full_site_promotion_remark'] ?? 'Remark: %s';
                if ($currentPromotion) {
                    $promotionText = \App\Models\Torrent::$promotionTypes[$currentPromotion['global_sp_state']]['text'] ?? '';
                    $msg = sprintf($lang_functions['full_site_promotion_in_effect'], $promotionText);
                    if (!empty($currentPromotion['begin']) || !empty($currentPromotion['deadline'])) {
                        $timeRange = sprintf($lang_functions['full_site_promotion_time_range'], $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>' . $timeRange;
                    }
                    if (!empty($currentPromotion['remark'])) {
                        $msg .= '<br/>' . sprintf($remarkTpl, $currentPromotion['remark']);
                    }
                    msgalert("torrents.php", $msg, "green");
                }
                if ($upcomingPromotion) {
                    $promotionText = \App\Models\Torrent::$promotionTypes[$upcomingPromotion['global_sp_state']]['text'] ?? '';
                    $msg = sprintf($lang_functions['full_site_promotion_upcoming'] ?? 'Upcoming full site [%s]', $promotionText);
                    if (!empty($upcomingPromotion['begin']) || !empty($upcomingPromotion['deadline'])) {
                        $timeRange = sprintf($lang_functions['full_site_promotion_time_range'], $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>' . $timeRange;
                    }
                    if (!empty($upcomingPromotion['remark'])) {
                        $msg .= '<br/>' . sprintf($remarkTpl, $upcomingPromotion['remark']);
                    }
                    msgalert("torrents.php", $msg, "blue");
                }
                if ($CURUSER['leechwarn'] == 'yes') {
                    $kicktimeout = gettime($CURUSER['leechwarnuntil'], false, false, true);
                    $text = $lang_functions['text_please_improve_ratio_within'] . $kicktimeout . $lang_functions['text_or_you_will_be_banned'];
                    msgalert("faq.php#id17", $text, "orange");
                }
                if ($deletenotransfertwo_account) {
                    if ($CURUSER['downloaded'] == 0 && ($CURUSER['uploaded'] == 0 || $CURUSER['uploaded'] == $iniupload_main)) {
                        $neverdelete_account = $neverdelete_account <= UC_VIP ? $neverdelete_account : UC_VIP;
                        if (get_user_class() < $neverdelete_account) {
                            $secs = $deletenotransfertwo_account * 24 * 60 * 60;
                            $addedtime = strtotime($CURUSER['added']);
                            if (TIMENOW > $addedtime + $secs / 3) {
                                $kicktimeout = gettime(date("Y-m-d H:i:s", $addedtime + $secs), false, false, true);
                                $text = $lang_functions['text_please_download_something_within'] . $kicktimeout . $lang_functions['text_inactive_account_be_deleted'];
                                msgalert("rules.php", $text, "gray");
                            }
                        }
                    }
                }
                if ($CURUSER['showclienterror'] == 'yes') {
                    $text = $lang_functions['text_banned_client_warning'];
                    msgalert("faq.php#id29", $text, "black");
                }
                if ($unread) {
                    $text = $lang_functions['text_you_have'] . $unread . $lang_functions['text_new_message'] . add_s($unread) . $lang_functions['text_click_here_to_read'];
                    msgalert("messages.php", $text, "red");
                }
                \App\Utils\MsgAlert::getInstance()->render();
                /*
                	$pending_invitee = $Cache->get_value('user_'.$CURUSER["id"].'_pending_invitee_count');
                	if ($pending_invitee == ""){
                		$pending_invitee = get_row_count("users","WHERE status = 'pending' AND invited_by = ".\App\Support\LegacyDb::escape($CURUSER['id']));
                		$Cache->cache_value('user_'.$CURUSER["id"].'_pending_invitee_count', $pending_invitee, 900);
                	}
                	if ($pending_invitee > 0)
                	{
                		$text = $lang_functions['text_your_friends'].add_s($pending_invitee).is_or_are($pending_invitee).$lang_functions['text_awaiting_confirmation'];
                		msgalert("invite.php?id=".$CURUSER['id'],$text, "red");
                	}*/
                $settings_script_name = $_SERVER["SCRIPT_FILENAME"];
                if (!preg_match("/index/i", $settings_script_name)) {
                    $new_news = $Cache->get_value('user_' . $CURUSER["id"] . '_unread_news_count');
                    if ($new_news == "") {
                        $new_news = get_row_count("news", "WHERE notify = 'yes' AND added > " . \App\Support\LegacyDb::escape($CURUSER['last_home']));
                        $Cache->cache_value('user_' . $CURUSER["id"] . '_unread_news_count', $new_news, 300);
                    }
                    if ($new_news > 0) {
                        $text = $lang_functions['text_there_is'] . is_or_are($new_news) . $new_news . $lang_functions['text_new_news'];
                        msgalert("index.php", $text, "green");
                    }
                }
                //Staff message, not only staff member
                //    $cacheKey = 'staff_new_message_count_' . $CURUSER['id'];
                //    $nummessages = $Cache->get_value($cacheKey);
                $nummessages = \App\Repositories\MessageRepository::getStaffMessageCountCache($CURUSER['id'], 'new');
                if ($nummessages === false) {
                    $nummessages = \App\Repositories\MessageRepository::countStaffMessage($CURUSER['id'], 0);
                    //        $Cache->cache_value($cacheKey, $nummessages, 900);
                    \App\Repositories\MessageRepository::updateStaffMessageCountCache($CURUSER['id'], 'new', $nummessages);
                }
                if ($nummessages > 0) {
                    $text = $lang_functions['text_there_is'] . is_or_are($nummessages) . $nummessages . $lang_functions['text_new_staff_message'] . add_s($nummessages);
                    msgalert("staffbox.php", $text, "blue");
                }
                //torrent approval
                if (user_can('torrent-approval') && get_setting('torrent.approval_status_none_visible') == 'no') {
                    $cacheKey = 'TORRENT_APPROVAL_NONE';
                    $toApprovalCounts = $Cache->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = get_row_count('torrents', 'where approval_status = 0');
                        $Cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    if ($toApprovalCounts) {
                        msgalert('torrents.php?approval_status=0&incldead=0', sprintf($lang_functions['text_torrent_to_approval'], is_or_are($toApprovalCounts), $toApprovalCounts, add_s($toApprovalCounts)), 'darkred');
                    }
                }
                //seed box approval
                if (get_user_class() >= \App\Models\User::CLASS_ADMINISTRATOR && get_setting('seed_box.enabled') == 'yes') {
                    $cacheKey = \App\Repositories\SeedBoxRepository::APPROVAL_COUNT_CACHE_KEY;
                    $toApprovalCounts = $Cache->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = get_row_count('seed_box_records', 'where status = 0');
                        $Cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    if ($toApprovalCounts) {
                        msgalert('/nexusphp/system/seed-box-records?tableFilters[status][value]=0', sprintf($lang_functions['text_seed_box_record_to_approval'], is_or_are($toApprovalCounts), $toApprovalCounts, add_s($toApprovalCounts)), 'darkred');
                    }
                }
                if (user_can('staffmem')) {
                    if (($complaints = $Cache->get_value('COMPLAINTS_COUNT_CACHE')) === false) {
                        $complaints = get_row_count('complains', 'WHERE answered = 0');
                        $Cache->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
                    }
                    if ($complaints) {
                        msgalert('complains.php?action=list', sprintf($lang_functions['text_complains'], is_or_are($complaints), $complaints, add_s($complaints)), 'darkred');
                    }
                    $numreports = $Cache->get_value('staff_new_report_count');
                    if ($numreports == "") {
                        $numreports = get_row_count("reports", "WHERE dealtwith=0");
                        $Cache->cache_value('staff_new_report_count', $numreports, 900);
                    }
                    if ($numreports) {
                        $text = $lang_functions['text_there_is'] . is_or_are($numreports) . $numreports . $lang_functions['text_new_report'] . add_s($numreports);
                        msgalert("reports.php", $text, "blue");
                    }
                    $numcheaters = $Cache->get_value('staff_new_cheater_count');
                    if ($numcheaters == "") {
                        $numcheaters = get_row_count("cheaters", "WHERE dealtwith=0");
                        $Cache->cache_value('staff_new_cheater_count', $numcheaters, 900);
                    }
                    if ($numcheaters) {
                        $text = $lang_functions['text_there_is'] . is_or_are($numcheaters) . $numcheaters . $lang_functions['text_new_suspected_cheater'] . add_s($numcheaters);
                        msgalert("cheaterbox.php", $text, "blue");
                    }
                }
                //show the exam info
                $exam = new \Nexus\Exam\Exam();
                $currentExam = $exam->getCurrent($CURUSER['id']);
                if (!empty($currentExam['html'])) {
                    msgalert($currentExam['exam']->type == \App\Models\Exam::TYPE_TASK ? "task.php" : "messages.php", $currentExam['html'], $currentExam['exam']->background_color ?? 'blue');
                }
            }
            if ($offlinemsg) {
                print "<p><table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='padding: 10px; background: red' class=\"text\" align=\"center\">\n";
                print "<font color=\"white\">" . $lang_functions['text_website_offline_warning'] . "</font>";
                print "</td></tr></table></p><br />\n";
            }
        }
    }
    public static function footer()
    {
        global $SITENAME, $BASEURL, $Cache, $datefounded, $tstart, $icplicense_main, $add_key_shortcut, $query_name, $USERUPDATESET, $CURUSER, $enablesqldebug_tweak, $sqldebug_tweak, $analyticscode_tweak;
        global $hook;
        print "</td></tr></table>";
        print "<div id=\"footer\">";
        print "<div style=\"margin-top: 10px; margin-bottom: 30px;\" align=\"center\">";
        if ($CURUSER) {
            if (count($USERUPDATESET)) {
                \Nexus\Database\NexusDB::getInstance()->query("UPDATE users SET " . join(",", $USERUPDATESET) . " WHERE id = " . $CURUSER['id']);
            }
        }
        // Variables for End Time
        $tend = microtime(true);
        $totaltime = $tend - nexus()->getStartTimestamp();
        $year = substr($datefounded, 0, 4);
        $yearfounded = $year ? $year : 2007;
        print " (c) " . " <a href=\"" . get_protocol_prefix() . $BASEURL . "\" target=\"_self\">" . $SITENAME . "</a> " . ($icplicense_main ? " " . $icplicense_main . " " : "") . (date("Y") != $yearfounded ? $yearfounded . "-" : "") . date("Y") . " " . VERSION . "<br /><br />";
        printf("[page created in <b> %s </b> sec", sprintf("%.3f", $totaltime));
        $debugQuery = $enablesqldebug_tweak == 'yes' && get_user_class() >= $sqldebug_tweak;
        if ($debugQuery) {
            $query_name_laravel = last_query(true);
            $dbQueryCount = count($query_name) + count($query_name_laravel);
        } else {
            $query_name_laravel = [];
            $dbQueryCount = count($query_name) + last_query('COUNT');
        }
        print " with <b>" . $dbQueryCount . "</b> db queries, <b>" . $Cache->getCacheReadTimes() . "</b> reads and <b>" . $Cache->getCacheWriteTimes() . "</b> writes of Redis and <b>" . mksize(memory_get_usage()) . "</b> ram]";
        print "</div>\n";
        if ($debugQuery) {
            print "<div id=\"sql_debug\" style='text-align: left;'>SQL query list: <ul>";
            foreach ($query_name as $query) {
                print sprintf('<li>%s [%s]</li>', htmlspecialchars($query['query']), $query['time']);
            }
            foreach ($query_name_laravel as $query) {
                print sprintf('<li>%s [%s ms]</li>', htmlspecialchars($query['raw_query']), $query['time']);
            }
            print "</ul>";
            print "Redis key read: <ul>";
            foreach ($Cache->getKeyHits('read') as $keyName => $hits) {
                print "<li>" . htmlspecialchars($keyName) . " : " . $hits . "</li>";
            }
            print "</ul>";
            print "Redis key write: <ul>";
            foreach ($Cache->getKeyHits('write') as $keyName => $hits) {
                print "<li>" . htmlspecialchars($keyName) . " : " . $hits . "</li>";
            }
            print "</ul>";
            print "</div>";
        }
        if ($add_key_shortcut != "") {
            print $add_key_shortcut;
        }
        print "</div>";
        if ($analyticscode_tweak) {
            print "\n" . $analyticscode_tweak . "\n";
        }
        //	$hook->dump();
        do_action('nexus_footer');
        foreach (\Nexus\Nexus::getAppendFooters() as $value) {
            print $value;
        }
        $js = <<<JS
        <script type="application/javascript" src="js/nexus.js"></script>
        <script type="application/javascript" src="js/medium-zoom.min.js"></script>
        <script type="application/javascript" src="vendor/jquery-goup-1.1.3/jquery.goup.min.js"></script>
        <script>
        jQuery(document).ready(function(){
            jQuery.goup()
            mediumZoom('[data-zoomable]')
        });
        </script>
        JS;
        print $js;
        print '<img id="nexus-preview" style="display: none; position: absolute" src="" />';
        print "</body></html>";
        //echo replacePngTags(ob_get_clean());
        //	unset($_SESSION['queries']);
    }
}