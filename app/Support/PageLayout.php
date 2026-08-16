<?php

namespace App\Support;

use App\Models\SearchBox;
use App\Repositories\PageLayoutRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class PageLayout
{
    /**
     * @param  string  $title
     * @param  bool  $msgalert
     * @param  string  $script
     * @param  string  $place
     * @return void
     */
    private static ?PageLayoutContext $context = null;

    public static function setContext(PageLayoutContext $context): void
    {
        self::$context = $context;
    }

    public static function getContext(): ?PageLayoutContext
    {
        return self::$context;
    }

    public static function header(string $title = "", bool $msgalert = true, string $script = "", string $place = ""): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $context->cache?->setLanguage($context->langDir);
        $cssupdatedate = $context->cssDateTweak;
        //Insert old ip into iplog
        if ($context->user) {
            //		if ($iplog1 == "yes") {
            //			if (($oldip != $context->user["ip"]) && $context->user["ip"])
            //			sql_query("INSERT INTO iplog (ip, userid, access) VALUES (" . sqlesc($context->user['ip']) . ", " . $context->user['id'] . ", '" . $context->user['last_access'] . "')");
            //		}
            // Per-request access tracking is handled by PageLayoutRepository::prepareAccess().
        }
        //header("Content-Type: text/html; charset=utf-8; Cache-control:private");
        //header("Pragma: No-cache");
        if ($title == "") {
            $title = $context->siteName;
        } else {
            $title = $context->siteName . " :: " . htmlspecialchars($title);
        }
        if ($context->titleKeywordsTweak) {
            $title .= " " . htmlspecialchars($context->titleKeywordsTweak);
        }
        $title .= " - Powered by " . PROJECTNAME;
        if ($context->siteOnline == "no") {
            if ($context->userClass() < $context->adminClass) {
                throw new HttpResponseException(new Response((string) ($context->lang['std_site_down_for_maintenance'] ?? 'Site down for maintenance'), 503));
            } else {
                $context->offlineMsg = true;
            }
        }
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php 
        if ($context->metaKeywordsTweak) {
            ?>
<meta name="keywords" content="<?php 
            echo htmlspecialchars($context->metaKeywordsTweak);
            ?>" />
<?php 
        }
        if ($context->metaDescriptionTweak) {
            ?>
<meta name="description" content="<?php 
            echo htmlspecialchars($context->metaDescriptionTweak);
            ?>" />
<?php 
        }
        ?>
<meta name="generator" content="<?php 
        echo PROJECTNAME;
        ?>" />
<?php 
        print Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        $css_uri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        $cssupdatedate = $cssupdatedate ? "?" . htmlspecialchars($cssupdatedate) : "";
        ?>
<title><?php 
        echo $title;
        ?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="<?php 
        echo $context->siteName;
        ?> Torrents" href="opensearch.php" />
<link rel="stylesheet" href="<?php 
        echo Style::fontCssUri($context->userFontSize()) . $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css<?php 
        echo $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php 
        echo Forum::picFolder($context->langDir) . "/forumsprites.css" . $cssupdatedate;
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
        if ($context->user) {
            //	$caticonrow = get_category_icon_row($context->user['caticon']);
            //	if($caticonrow['cssfile']){
            $requireSearchBoxIdAr = \App\Support\SearchBox::requiredIds();
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
        \App\Support\Hooks::doAction('nexus_header');
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
        echo $context->user !== null ? CONTENT_WIDTH + 28.66 : CONTENT_WIDTH;
        ?>px">
	<tr>
		<td class="clear">
<?php 
        if ($context->logoMain == "") {
            ?>
			<div class="logo"><?php 
            echo htmlspecialchars($context->siteName);
            ?></div>
			<div class="slogan"><?php 
            echo htmlspecialchars($context->slogan);
            ?></div>
<?php 
        } else {
            ?>
			<div class="logo_img"><img src="<?php 
            echo $context->logoMain;
            ?>" alt="<?php 
            echo htmlspecialchars($context->siteName);
            ?>" title="<?php 
            echo htmlspecialchars($context->siteName);
            ?> - <?php 
            echo htmlspecialchars($context->slogan);
            ?>" /></div>
<?php 
        }
        ?>
		</td>
		<td class="clear nowrap" align="right" valign="middle">
<?php 
        if ($context->enableDonation == 'yes') {
            ?>
			<a href="donate.php"><img src="<?php 
            echo Forum::picFolder($context->langDir);
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
        if (!$context->user) {
            ?>
			<a href="login.php"><font class="big"><b><?php 
            echo $context->lang['text_login'];
            ?></b></font></a> / <a href="signup.php"><font class="big"><b><?php 
            echo $context->lang['text_signup'];
            ?></b></font></a>
<?php 
        } else {
            \App\Support\Frame::mainFrameOpen();
            print $context->menuHtml;
            \App\Support\Frame::mainFrameClose();
            $datum = getdate();
            $datum["hours"] = sprintf("%02.0f", $datum["hours"]);
            $datum["minutes"] = sprintf("%02.0f", $datum["minutes"]);
            $ratio = \App\Support\Ratio::forUserId($context->user['id']);
            //// check every 15 minutes //////////////////
            $messages = $context->cache->get_value('user_' . $context->user["id"] . '_inbox_count');
            if ($messages == "") {
                $messages = app(PageLayoutRepository::class)->getInboxCount((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_inbox_count', $messages, 900);
            }
            $outmessages = $context->cache->get_value('user_' . $context->user["id"] . '_outbox_count');
            if ($outmessages == "") {
                $outmessages = app(PageLayoutRepository::class)->getOutboxCount((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_outbox_count', $outmessages, 900);
            }
            if (!$connect = $context->cache->get_value('user_' . $context->user["id"] . '_connect')) {
                $connect = app(PageLayoutRepository::class)->getConnectable((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_connect', $connect, 900);
            }
            if ($connect == "yes") {
                $connectable = "<b><font color=\"green\">" . $context->lang['text_yes'] . "</font></b>";
            } elseif ($connect == 'no') {
                $connectable = "<a href=\"faq.php#id21\"><b><font color=\"red\">" . $context->lang['text_no'] . "</font></b></a>";
            } else {
                $connectable = $context->lang['text_unknown'];
            }
            //// check every 60 seconds //////////////////
            $activeseed = $context->cache->get_value('user_' . $context->user["id"] . '_active_seed_count');
            if ($activeseed == "") {
                $activeseed = app(PageLayoutRepository::class)->getActiveSeedCount((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_active_seed_count', $activeseed, 60);
            }
            $activeleech = $context->cache->get_value('user_' . $context->user["id"] . '_active_leech_count');
            if ($activeleech == "") {
                $activeleech = app(PageLayoutRepository::class)->getActiveLeechCount((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_active_leech_count', $activeleech, 60);
            }
            $unread = $context->cache->get_value('user_' . $context->user["id"] . '_unread_message_count');
            if ($unread == "") {
                $unread = app(PageLayoutRepository::class)->getUnreadMessageCount((int) $context->user["id"]);
                $context->cache->cache_value('user_' . $context->user["id"] . '_unread_message_count', $unread, 60);
            }
            $inboxpic = "<img class=\"" . ($unread ? "inboxnew" : "inbox") . "\" src=\"pic/trans.gif\" alt=\"inbox\" title=\"" . ($unread ? $context->lang['title_inbox_new_messages'] : $context->lang['title_inbox_no_new_messages']) . "\" />";
            //    $attend_desk = new Attendance($context->user['id']);
            //    $attendance = $attend_desk->check();
            $attendanceRep = new \App\Repositories\AttendanceRepository();
            $attendance = $attendanceRep->getAttendance($context->user['id'], date('Ymd'));
            ?>

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left">
            <span class="medium">
                <?php 
            echo $context->lang['text_welcome_back'];
            ?>, <?php 
            echo \App\Support\UserDisplay::username($context->user['id']);
            ?>
                [<a href="logout.php"><?php 
            echo $context->lang['text_logout'];
            ?></a>]
                [<a href="usercp.php"><?php 
            echo $context->lang['text_user_cp'];
            ?></a>]
                <?php 
            if ($context->userClass() >= $context->moderatorClass) {
                ?> [<a href="staffpanel.php"><?php 
                echo $context->lang['text_staff_panel'];
                ?></a>] <?php 
            }
            ?>
                <?php 
            if ($context->userClass() >= $context->sysopClass) {
                ?> [<a href="settings.php"><?php 
                echo $context->lang['text_site_settings'];
                ?></a>]<?php 
            }
            ?>
                [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0"><?php 
            echo $context->lang['text_bookmarks'];
            ?></a>]
                <font class = 'color_bonus'><?php 
            echo $context->lang['text_bonus'];
            ?></font>[<a href="mybonus.php"><?php 
            echo $context->lang['text_use'];
            ?></a>]: <?php 
            echo number_format($context->user['seedbonus'], 1);
            ?>
                <?php 
            if ($attendance) {
                printf(' <a href="attendance.php" class="">' . $context->lang['text_attended'] . '</a>', $attendance->points, $context->user['attendance_card']);
            } else {
                printf(' <a href="attendance.php" class="faqlink">%s</a>', $context->lang['text_attendance']);
            }
            ?>
                <a href="medal.php">[<?php 
            echo Locale::trans('medal.label');
            ?>]</a>
                <a href="task.php">[<?php 
            echo Locale::trans('exam.type_task');
            ?>]</a>
                <font class = 'color_invite'><?php 
            echo $context->lang['text_invite'];
            ?></font>[<a href="invite.php?id=<?php 
            echo $context->user['id'];
            ?>"><?php 
            echo $context->lang['text_send'];
            ?></a>]: <?php 
            echo sprintf('%s(%s)', $context->user['invites'], (new \App\Repositories\PageLayoutRepository())->getPendingInviteCount((int) $context->user['id']));
            ?>
                <?php 
            if ($context->userClass() >= \App\Models\User::getAccessAdminClassMin()) {
                printf('[<a href="%s" target="_blank">%s</a>]', \App\Support\Env::get('FILAMENT_PATH', 'nexusphp'), $context->lang['text_management_system']);
            }
            ?>
                <br />
	            <font class="color_ratio"><?php 
            echo $context->lang['text_ratio'];
            ?></font> <?php 
            echo $ratio;
            ?>
                <font class='color_uploaded'><?php 
            echo $context->lang['text_uploaded'];
            ?></font> <?php 
            echo \App\Support\Format::size($context->user['uploaded']);
            ?>
                <font class='color_downloaded'> <?php 
            echo $context->lang['text_downloaded'];
            ?></font> <?php 
            echo \App\Support\Format::size($context->user['downloaded']);
            ?>
                <font class='color_active'><?php 
            echo $context->lang['text_active_torrents'];
            ?></font> <img class="arrowup" alt="Torrents seeding" title="<?php 
            echo $context->lang['title_torrents_seeding'];
            ?>" src="pic/trans.gif" /><?php 
            echo $activeseed;
            ?>  <img class="arrowdown" alt="Torrents leeching" title="<?php 
            echo $context->lang['title_torrents_leeching'];
            ?>" src="pic/trans.gif" /><?php 
            echo $activeleech;
            ?>&nbsp;&nbsp;
                <font class='color_connectable'><?php 
            echo $context->lang['text_connectable'];
            ?></font><?php 
            echo $connectable;
            ?> <?php 
            echo Slots::display((int) $context->user['uploaded'], (int) $context->user['downloaded'], $context->maxdlSystem, $context->userClass(), $context->vipClass, $context->lang['text_slots'] ?? '', $context->lang['text_unlimited'] ?? '');
            ?>
                <?php 
            if (\App\Models\HitAndRun::getIsEnabled()) {
                ?><font class='color_bonus'>H&R: </font> <?php 
                echo sprintf('[<a href="myhr.php">%s</a>]', (new \App\Repositories\HitAndRunRepository())->getStatusStats($context->user['id']));
            }
            ?>
            </span>
        </td>
                <?php 
            if (Settings::get('main.enable_global_search') == 'yes') {
                ?>
        <td class="bottom" align="left" style="border: none">
            <form action="search.php" method="get" target="<?php 
                echo \Nexus\Nexus::instance()->getScript() == 'search' ? '_self' : '_blank';
                ?>">
                <div style="display: flex;align-items: center">
                    <div style="display: flex;flex-direction: column">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="<?php 
                echo $context->requestSearch ?? '';
                ?>" placeholder="<?php 
                echo Locale::trans('search.search_keyword');
                ?>"/></span>
                        </div>
                        <div>
                            <span><?php 
                echo \App\Support\SearchBox::areaSelect($context->requestSearchArea ?? '', ['style' => 'width: 88px']);
                ?></span>
                        </div>
                    </div>
                    <div><input type="submit" value="<?php 
                echo Locale::trans('search.global_search');
                ?>" style="width: 39px;white-space: break-spaces;padding: 0" /></div>
                </div>
            </form>
        </td>
                <?php 
            }
            ?>
	<td class="bottom" align="right"><span class="medium">
<?php 
            if (Permissions::userCan('staffmem', false, (int) ($context->user['id'] ?? 0))) {
                $totalreports = $context->cache->get_value('staff_report_count');
                if ($totalreports == "") {
                    $totalreports = app(PageLayoutRepository::class)->getTotalReports();
                    $context->cache->cache_value('staff_report_count', $totalreports, 900);
                }
                $totalcheaters = $context->cache->get_value('staff_cheater_count');
                if ($totalcheaters == "") {
                    $totalcheaters = app(PageLayoutRepository::class)->getTotalCheaters();
                    $context->cache->cache_value('staff_cheater_count', $totalcheaters, 900);
                }
                print "<a href=\"cheaterbox.php\"><img class=\"cheaterbox\" alt=\"cheaterbox\" title=\"" . $context->lang['title_cheaterbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalcheaters . "  <a href=\"reports.php\"><img class=\"reportbox\" alt=\"reportbox\" title=\"" . $context->lang['title_reportbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalreports;
            }
            print " <a href=\"friends.php\"><img class=\"buddylist\" alt=\"Buddylist\" title=\"" . $context->lang['title_buddylist'] . "\" src=\"pic/trans.gif\" /></a>";
            print " <a href=\"getrss.php\"><img class=\"rss\" alt=\"RSS\" title=\"" . $context->lang['title_get_rss'] . "\" src=\"pic/trans.gif\" /></a>";
            print '<br/>';
            //echo $context->lang['text_the_time_is_now'].$datum['hours'].":".$datum['minutes'] . '<br />';
            //	$cacheKey = "staff_message_count_" . $context->user['id'];
            //    $totalsm = $context->cache->get_value($cacheKey);
            $totalsm = \App\Repositories\MessageRepository::getStaffMessageCountCache($context->user['id'], 'total');
            if ($totalsm === false) {
                $totalsm = \App\Repositories\MessageRepository::countStaffMessage($context->user['id']);
                //        $context->cache->cache_value($cacheKey, $totalsm, 900);
                \App\Repositories\MessageRepository::updateStaffMessageCountCache($context->user['id'], 'total', $totalsm);
            }
            if ($totalsm > 0) {
                print "  <a href=\"staffbox.php\"><img class=\"staffbox\" alt=\"staffbox\" title=\"" . $context->lang['title_staffbox'] . "\" src=\"pic/trans.gif\" />  </a>" . $totalsm . "  ";
            }
            print "<a href=\"messages.php\">" . $inboxpic . "</a> " . ($messages ? $messages . " (" . $unread . $context->lang['text_message_new'] . ")" : "0");
            print "  <a href=\"messages.php?action=viewmailbox&amp;box=-1\"><img class=\"sentbox\" alt=\"sentbox\" title=\"" . $context->lang['title_sentbox'] . "\" src=\"pic/trans.gif\" /></a> " . ($outmessages ? $outmessages : "0");
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
                $remarkTpl = $context->lang['full_site_promotion_remark'] ?? 'Remark: %s';
                if ($currentPromotion) {
                    $promotionText = \App\Models\Torrent::$promotionTypes[$currentPromotion['global_sp_state']]['text'] ?? '';
                    $msg = sprintf($context->lang['full_site_promotion_in_effect'], $promotionText);
                    if (!empty($currentPromotion['begin']) || !empty($currentPromotion['deadline'])) {
                        $timeRange = sprintf($context->lang['full_site_promotion_time_range'], $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>' . $timeRange;
                    }
                    if (!empty($currentPromotion['remark'])) {
                        $msg .= '<br/>' . sprintf($remarkTpl, $currentPromotion['remark']);
                    }
                    \App\Support\Html::messageAlertVoid("torrents.php", $msg, "green");
                }
                if ($upcomingPromotion) {
                    $promotionText = \App\Models\Torrent::$promotionTypes[$upcomingPromotion['global_sp_state']]['text'] ?? '';
                    $msg = sprintf($context->lang['full_site_promotion_upcoming'] ?? 'Upcoming full site [%s]', $promotionText);
                    if (!empty($upcomingPromotion['begin']) || !empty($upcomingPromotion['deadline'])) {
                        $timeRange = sprintf($context->lang['full_site_promotion_time_range'], $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>' . $timeRange;
                    }
                    if (!empty($upcomingPromotion['remark'])) {
                        $msg .= '<br/>' . sprintf($remarkTpl, $upcomingPromotion['remark']);
                    }
                    \App\Support\Html::messageAlertVoid("torrents.php", $msg, "blue");
                }
                if ($context->user['leechwarn'] == 'yes') {
                    $kicktimeout = \App\Support\Time::format($context->user['leechwarnuntil'], false, false, true);
                    $text = $context->lang['text_please_improve_ratio_within'] . $kicktimeout . $context->lang['text_or_you_will_be_banned'];
                    \App\Support\Html::messageAlertVoid("faq.php#id17", $text, "orange");
                }
                if ($context->deleteNotTransferTwoAccount) {
                    if ($context->user['downloaded'] == 0 && ($context->user['uploaded'] == 0 || $context->user['uploaded'] == $context->iniUploadMain)) {
                        $context->neverDeleteAccount = $context->neverDeleteAccount <= $context->vipClass ? $context->neverDeleteAccount : $context->vipClass;
                        if ($context->userClass() < $context->neverDeleteAccount) {
                            $secs = $context->deleteNotTransferTwoAccount * 24 * 60 * 60;
                            $addedtime = strtotime($context->user['added']);
                            if (TIMENOW > $addedtime + $secs / 3) {
                                $kicktimeout = \App\Support\Time::format(date("Y-m-d H:i:s", $addedtime + $secs), false, false, true);
                                $text = $context->lang['text_please_download_something_within'] . $kicktimeout . $context->lang['text_inactive_account_be_deleted'];
                                \App\Support\Html::messageAlertVoid("rules.php", $text, "gray");
                            }
                        }
                    }
                }
                if ($context->user['showclienterror'] == 'yes') {
                    $text = $context->lang['text_banned_client_warning'];
                    \App\Support\Html::messageAlertVoid("faq.php#id29", $text, "black");
                }
                if ($unread) {
                    $text = $context->lang['text_you_have'] . $unread . $context->lang['text_new_message'] . Strings::addS($unread) . $context->lang['text_click_here_to_read'];
                    \App\Support\Html::messageAlertVoid("messages.php", $text, "red");
                }
                \App\Utils\MsgAlert::getInstance()->render();
                /*
                	$pending_invitee = $context->cache->get_value('user_'.$context->user["id"].'_pending_invitee_count');
                	if ($pending_invitee == ""){
                		$pending_invitee = get_row_count("users","WHERE status = 'pending' AND invited_by = ".\App\Support\LegacyDb::escape($context->user['id']));
                		$context->cache->cache_value('user_'.$context->user["id"].'_pending_invitee_count', $pending_invitee, 900);
                	}
                	if ($pending_invitee > 0)
                	{
                		$text = $context->lang['text_your_friends'].Strings::addS($pending_invitee).Strings::isOrAre($pending_invitee).$context->lang['text_awaiting_confirmation'];
                		msgalert("invite.php?id=".$context->user['id'],$text, "red");
                	}*/
                $settings_script_name = $context->scriptFileName;
                if (!preg_match("/index/i", $settings_script_name)) {
                    $new_news = $context->cache->get_value('user_' . $context->user["id"] . '_unread_news_count');
                    if ($new_news == "") {
                        $lastHome = $context->user['last_home'] ?? null;
                        $new_news = app(PageLayoutRepository::class)->getUnreadNewsCount($lastHome);
                        $context->cache->cache_value('user_' . $context->user["id"] . '_unread_news_count', $new_news, 300);
                    }
                    if ($new_news > 0) {
                        $text = $context->lang['text_there_is'] . Strings::isOrAre($new_news) . $new_news . $context->lang['text_new_news'];
                        \App\Support\Html::messageAlertVoid("index.php", $text, "green");
                    }
                }
                //Staff message, not only staff member
                //    $cacheKey = 'staff_new_message_count_' . $context->user['id'];
                //    $nummessages = $context->cache->get_value($cacheKey);
                $nummessages = \App\Repositories\MessageRepository::getStaffMessageCountCache($context->user['id'], 'new');
                if ($nummessages === false) {
                    $nummessages = \App\Repositories\MessageRepository::countStaffMessage($context->user['id'], 0);
                    //        $context->cache->cache_value($cacheKey, $nummessages, 900);
                    \App\Repositories\MessageRepository::updateStaffMessageCountCache($context->user['id'], 'new', $nummessages);
                }
                if ($nummessages > 0) {
                    $text = $context->lang['text_there_is'] . Strings::isOrAre($nummessages) . $nummessages . $context->lang['text_new_staff_message'] . Strings::addS($nummessages);
                    \App\Support\Html::messageAlertVoid("staffbox.php", $text, "blue");
                }
                //torrent approval
                if (Permissions::userCan('torrent-approval', false, (int) ($context->user['id'] ?? 0)) && Settings::get('torrent.approval_status_none_visible') == 'no') {
                    $cacheKey = 'TORRENT_APPROVAL_NONE';
                    $toApprovalCounts = $context->cache->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = app(PageLayoutRepository::class)->getTorrentApprovalNoneCount();
                        $context->cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    if ($toApprovalCounts) {
                        \App\Support\Html::messageAlertVoid('torrents.php?approval_status=0&incldead=0', sprintf($context->lang['text_torrent_to_approval'], Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)), 'darkred');
                    }
                }
                //seed box approval
                if ($context->userClass() >= \App\Models\User::CLASS_ADMINISTRATOR && Settings::get('seed_box.enabled') == 'yes') {
                    $cacheKey = \App\Repositories\SeedBoxRepository::APPROVAL_COUNT_CACHE_KEY;
                    $toApprovalCounts = $context->cache->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = app(PageLayoutRepository::class)->getSeedBoxApprovalCount();
                        $context->cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    if ($toApprovalCounts) {
                        \App\Support\Html::messageAlertVoid('/nexusphp/system/seed-box-records?tableFilters[status][value]=0', sprintf($context->lang['text_seed_box_record_to_approval'], Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)), 'darkred');
                    }
                }
                if (Permissions::userCan('staffmem', false, (int) ($context->user['id'] ?? 0))) {
                    if (($complaints = $context->cache->get_value('COMPLAINTS_COUNT_CACHE')) === false) {
                        $complaints = app(PageLayoutRepository::class)->getOpenComplaintsCount();
                        $context->cache->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
                    }
                    if ($complaints) {
                        \App\Support\Html::messageAlertVoid('complains.php?action=list', sprintf($context->lang['text_complains'], Strings::isOrAre($complaints), $complaints, Strings::addS($complaints)), 'darkred');
                    }
                    $numreports = $context->cache->get_value('staff_new_report_count');
                    if ($numreports == "") {
                        $numreports = app(PageLayoutRepository::class)->getOpenReportsCount();
                        $context->cache->cache_value('staff_new_report_count', $numreports, 900);
                    }
                    if ($numreports) {
                        $text = $context->lang['text_there_is'] . Strings::isOrAre($numreports) . $numreports . $context->lang['text_new_report'] . Strings::addS($numreports);
                        \App\Support\Html::messageAlertVoid("reports.php", $text, "blue");
                    }
                    $numcheaters = $context->cache->get_value('staff_new_cheater_count');
                    if ($numcheaters == "") {
                        $numcheaters = app(PageLayoutRepository::class)->getOpenCheatersCount();
                        $context->cache->cache_value('staff_new_cheater_count', $numcheaters, 900);
                    }
                    if ($numcheaters) {
                        $text = $context->lang['text_there_is'] . Strings::isOrAre($numcheaters) . $numcheaters . $context->lang['text_new_suspected_cheater'] . Strings::addS($numcheaters);
                        \App\Support\Html::messageAlertVoid("cheaterbox.php", $text, "blue");
                    }
                }
                //show the exam info
                $exam = new \Nexus\Exam\Exam();
                $currentExam = $exam->getCurrent($context->user['id']);
                if (!empty($currentExam['html'])) {
                    \App\Support\Html::messageAlertVoid($currentExam['exam']->type == \App\Models\Exam::TYPE_TASK ? "task.php" : "messages.php", $currentExam['html'], $currentExam['exam']->background_color ?? 'blue');
                }
            }
            if ($context->offlineMsg) {
                print "<p><table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='padding: 10px; background: red' class=\"text\" align=\"center\">\n";
                print "<font color=\"white\">" . $context->lang['text_website_offline_warning'] . "</font>";
                print "</td></tr></table></p><br />\n";
            }
        }
    }
    /**
     * @return void
     */
    public static function footer(): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        print "</td></tr></table>";
        print "<div id=\"footer\">";
        print "<div style=\"margin-top: 10px; margin-bottom: 30px;\" align=\"center\">";
        // Variables for End Time
        $tend = microtime(true);
        $totaltime = $tend - \Nexus\Nexus::instance()->getStartTimestamp();
        $year = substr($context->dateFounded, 0, 4);
        $yearfounded = $year ? $year : 2007;
        print " (c) " . " <a href=\"" . Http::protocolPrefix(Url::isSecure()) . $context->baseUrl . "\" target=\"_self\">" . $context->siteName . "</a> " . ($context->icpLicenseMain ? " " . $context->icpLicenseMain . " " : "") . (date("Y") != $yearfounded ? $yearfounded . "-" : "") . date("Y") . " " . VERSION . "<br /><br />";
        printf("[page created in <b> %s </b> sec", sprintf("%.3f", $totaltime));
        $debugQuery = $context->enableSqlDebugTweak == 'yes' && $context->userClass() >= $context->sqlDebugTweak;
        if ($debugQuery) {
            $query_name_laravel = \App\Support\LegacyDb::lastQuery(true, 'json');
            $dbQueryCount = count($context->queryName) + count($query_name_laravel);
        } else {
            $query_name_laravel = [];
            $dbQueryCount = count($context->queryName) + \App\Support\LegacyDb::lastQuery('COUNT', 'json');
        }
        print " with <b>" . $dbQueryCount . "</b> db queries, <b>" . $context->cache->getCacheReadTimes() . "</b> reads and <b>" . $context->cache->getCacheWriteTimes() . "</b> writes of Redis and <b>" . \App\Support\Format::size(memory_get_usage()) . "</b> ram]";
        print "</div>\n";
        if ($debugQuery) {
            print "<div id=\"sql_debug\" style='text-align: left;'>SQL query list: <ul>";
            foreach ($context->queryName as $query) {
                print sprintf('<li>%s [%s]</li>', htmlspecialchars($query['query']), $query['time']);
            }
            foreach ($query_name_laravel as $query) {
                print sprintf('<li>%s [%s ms]</li>', htmlspecialchars($query['raw_query']), $query['time']);
            }
            print "</ul>";
            print "Redis key read: <ul>";
            foreach ($context->cache->getKeyHits('read') as $keyName => $hits) {
                print "<li>" . htmlspecialchars($keyName) . " : " . $hits . "</li>";
            }
            print "</ul>";
            print "Redis key write: <ul>";
            foreach ($context->cache->getKeyHits('write') as $keyName => $hits) {
                print "<li>" . htmlspecialchars($keyName) . " : " . $hits . "</li>";
            }
            print "</ul>";
            print "</div>";
        }
        if ($context->addKeyShortcut != "") {
            print $context->addKeyShortcut;
        }
        print "</div>";
        if ($context->analyticsCodeTweak) {
            print "\n" . $context->analyticsCodeTweak . "\n";
        }
        //	$hook->dump();
        \App\Support\Hooks::doAction('nexus_footer');
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