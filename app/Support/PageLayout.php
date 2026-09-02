<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ExamType;
use App\Enums\TorrentPromotion;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\TorrentState;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PageLayoutRepository;
use App\Repositories\SearchBoxRepository;
use App\Utils\MsgAlert;
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

    public static function resetState(): void
    {
        self::$context = null;
    }

    public static function header(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $cspNonce = (string) (request()->attributes->get('csp_nonce', ''));

        $context->cache?->setLanguage($context->langDir);
        $cssupdatedate = $context->cssDateTweak;
        // Insert old ip into iplog
        if ($context->user) {
            // Per-request access tracking is handled by app(PageLayoutRepository::class)->prepareAccess().
        }
        if ($title == '') {
            $title = $context->siteName;
        } else {
            $title = $context->siteName.' :: '.htmlspecialchars($title);
        }
        if ($context->titleKeywordsTweak) {
            $title .= ' '.htmlspecialchars($context->titleKeywordsTweak);
        }
        $title .= ' - Powered by '.PROJECTNAME;
        if ($context->siteOnline == 'no') {
            if ($context->userClass() < $context->adminClass) {
                throw new HttpResponseException(new Response((string) ($context->lang['std_site_down_for_maintenance'] ?? 'Site down for maintenance'), 503));
            } else {
                $context->offlineMsg = true;
            }
        }
        ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo htmlspecialchars(str_replace('_', '-', app()->getLocale())); ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
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
<meta name="csrf-token" content="<?php
        echo csrf_token();
        ?>" />
<?php
        $addiCode = Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        if ($cspNonce !== '' && $addiCode !== '') {
            // Inject CSP nonce into <style> tags within addicode.
            $addiCode = (string) preg_replace('/<style(?![^>]*\snonce=)/i', '<style nonce="'.$cspNonce.'"', $addiCode);
        }
        echo $addiCode;
        $css_uri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        $cssupdatedate = $cssupdatedate ? '?'.htmlspecialchars($cssupdatedate) : '';
        ?>
<title><?php
        echo $title;
        ?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="<?php
        echo $context->siteName;
        ?> Torrents" href="opensearch.php" />
<link rel="stylesheet" href="<?php
        echo Style::fontCssUri($context->userFontSize()).$cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css<?php
        echo $cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php
        echo Forum::picFolder($context->langDir).'/forumsprites.css'.$cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php
        echo $css_uri.'theme.css'.$cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="<?php
        echo $css_uri.'DomTT.css'.$cssupdatedate;
        ?>" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css<?php
        echo $cssupdatedate;
        ?>" type="text/css" />
<?php
        if ($context->user) {
            $requireSearchBoxIdAr = SearchBox::requiredIds();
            if (! empty($requireSearchBoxIdAr)) {
                $icons = app(SearchBoxRepository::class)->listIcon($requireSearchBoxIdAr);
                foreach ($icons as $icon) {
                    ?>
<link rel="stylesheet" href="<?php
                    echo htmlspecialchars(trim($icon['cssfile'] ?? '', '/')).$cssupdatedate;
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
        foreach (AssetAppender::getAppendHeaders() as $value) {
            echo $value;
        }
        ?>
<script type="text/javascript" nonce="<?php echo $cspNonce; ?>">
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
<a href="#main-content" class="skip-link" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" onfocus="this.style.left='0';this.style.width='auto';this.style.height='auto';" onblur="this.style.left='-9999px';this.style.width='1px';this.style.height='1px';">Skip to main content</a>
<table class="head" cellspacing="0" cellpadding="0" align="center" style="width: <?php
        echo $context->user !== null ? CONTENT_WIDTH + 28.66 : CONTENT_WIDTH;
        ?>px">
	<tr>
		<td class="clear">
<?php
        if ($context->logoMain == '') {
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
        if (! $context->user) {
            ?>
			<a href="login.php"><font class="big"><b><?php
            echo $context->lang['text_login'];
            ?></b></font></a> / <a href="signup.php"><font class="big"><b><?php
            echo $context->lang['text_signup'];
            ?></b></font></a>
<?php
        } else {
            Frame::mainFrameOpen();
            echo $context->menuHtml;
            Frame::mainFrameClose();
            $datum = getdate();
            $datum['hours'] = sprintf('%02.0f', $datum['hours']);
            $datum['minutes'] = sprintf('%02.0f', $datum['minutes']);
            $ratio = Ratio::forUserId($context->user['id']);
            // // check every 15 minutes //////////////////
            $messages = $context->cache?->get_value('user_'.$context->user['id'].'_inbox_count');
            if ($messages == '') {
                $messages = app(PageLayoutRepository::class)->getInboxCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_inbox_count', $messages, 900);
            }
            $outmessages = $context->cache?->get_value('user_'.$context->user['id'].'_outbox_count');
            if ($outmessages == '') {
                $outmessages = app(PageLayoutRepository::class)->getOutboxCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_outbox_count', $outmessages, 900);
            }
            $connect = $context->cache?->get_value('user_'.$context->user['id'].'_connect');
            if ($connect === false || $connect === null) {
                $connect = app(PageLayoutRepository::class)->getConnectable((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_connect', $connect, 900);
            }
            if ($connect === 1) {
                $connectable = '<b><font color="green">'.$context->lang['text_yes'].'</font></b>';
            } elseif ($connect === 0) {
                $connectable = '<a href="faq.php#id21"><b><font color="red">'.$context->lang['text_no'].'</font></b></a>';
            } else {
                $connectable = $context->lang['text_unknown'];
            }
            // // check every 60 seconds //////////////////
            $activeseed = $context->cache?->get_value('user_'.$context->user['id'].'_active_seed_count');
            if ($activeseed == '') {
                $activeseed = app(PageLayoutRepository::class)->getActiveSeedCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_active_seed_count', $activeseed, 60);
            }
            $activeleech = $context->cache?->get_value('user_'.$context->user['id'].'_active_leech_count');
            if ($activeleech == '') {
                $activeleech = app(PageLayoutRepository::class)->getActiveLeechCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_active_leech_count', $activeleech, 60);
            }
            $unread = $context->cache?->get_value('user_'.$context->user['id'].'_unread_message_count');
            if ($unread == '') {
                $unread = app(PageLayoutRepository::class)->getUnreadMessageCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_unread_message_count', $unread, 60);
            }
            $inboxpic = '<img class="'.($unread ? 'inboxnew' : 'inbox').'" src="pic/trans.gif" alt="inbox" title="'.($unread ? $context->lang['title_inbox_new_messages'] : $context->lang['title_inbox_no_new_messages']).'" />';
            $attendanceRep = app(AttendanceRepository::class);
            $attendance = $attendanceRep->getAttendance($context->user['id'], date('Ymd'));
            ?>

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left">
            <span class="medium">
                <?php
            echo $context->lang['text_welcome_back'];
            ?>, <?php
            echo UserDisplay::username($context->user['id']);
            ?>
                [<form method="post" action="logout.php" style="display:inline"><?php
            echo csrf_field();
            ?><button type="submit" style="background:none;border:none;padding:0;margin:0;color:inherit;cursor:pointer;text-decoration:underline;display:inline"><?php
            echo $context->lang['text_logout'];
            ?></button></form>]
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
                printf(' <a href="attendance.php" class="">'.$context->lang['text_attended'].'</a>', $attendance->points, $context->user['attendance_card']);
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
            echo sprintf('%s(%s)', $context->user['invites'], app(PageLayoutRepository::class)->getPendingInviteCount((int) $context->user['id']));
            ?>
                <?php
            if ($context->userClass() >= User::getAccessAdminClassMin()) {
                printf('[<a href="%s" target="_blank">%s</a>]', Env::get('FILAMENT_PATH', 'nexusphp'), $context->lang['text_management_system']);
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
            echo Format::size($context->user['uploaded']);
            ?>
                <font class='color_downloaded'> <?php
            echo $context->lang['text_downloaded'];
            ?></font> <?php
            echo Format::size($context->user['downloaded']);
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
            if (HitAndRun::getIsEnabled()) {
                ?><font class='color_bonus'>H&R: </font> <?php
                echo sprintf('[<a href="myhr.php">%s</a>]', app(HitAndRunRepository::class)->getStatusStats($context->user['id']));
            }
            ?>
            </span>
        </td>
                <?php
            if (Settings::get('main.enable_global_search') == 'yes') {
                ?>
        <td class="bottom" align="left" style="border: none">
            <form action="search.php" method="get" target="<?php
                echo RequestContext::instance()->getScript() == 'search' ? '_self' : '_blank';
                ?>">
                <div style="display: flex;align-items: center">
                    <div style="display: flex;flex-direction: column">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="<?php
                echo Html::escapeAttr((string) ($context->requestSearch ?? ''));
                ?>" placeholder="<?php
                echo Locale::trans('search.search_keyword');
                ?>"/></span>
                        </div>
                        <div>
                            <span><?php
                echo SearchBox::areaSelect($context->requestSearchArea ?? '', ['style' => 'width: 88px']);
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
                $totalreports = $context->cache?->get_value('staff_report_count');
                if ($totalreports == '') {
                    $totalreports = app(PageLayoutRepository::class)->getTotalReports();
                    $context->cache?->cache_value('staff_report_count', $totalreports, 900);
                }
                $totalcheaters = $context->cache?->get_value('staff_cheater_count');
                if ($totalcheaters == '') {
                    $totalcheaters = app(PageLayoutRepository::class)->getTotalCheaters();
                    $context->cache?->cache_value('staff_cheater_count', $totalcheaters, 900);
                }
                echo '<a href="cheaterbox.php"><img class="cheaterbox" alt="cheaterbox" title="'.$context->lang['title_cheaterbox'].'" src="pic/trans.gif" />  </a>'.$totalcheaters.'  <a href="reports.php"><img class="reportbox" alt="reportbox" title="'.$context->lang['title_reportbox'].'" src="pic/trans.gif" />  </a>'.$totalreports;
            }
            echo ' <a href="friends.php"><img class="buddylist" alt="Buddylist" title="'.$context->lang['title_buddylist'].'" src="pic/trans.gif" /></a>';
            echo ' <a href="getrss.php"><img class="rss" alt="RSS" title="'.$context->lang['title_get_rss'].'" src="pic/trans.gif" /></a>';
            echo '<br/>';
            $totalsm = app(MessageRepository::class)->getStaffMessageCountCache($context->user['id'], 'total');
            if ($totalsm === false) {
                $totalsm = app(MessageRepository::class)->countStaffMessage($context->user['id']);
                app(MessageRepository::class)->updateStaffMessageCountCache($context->user['id'], 'total', $totalsm);
            }
            if ($totalsm > 0) {
                echo '  <a href="staffbox.php"><img class="staffbox" alt="staffbox" title="'.$context->lang['title_staffbox'].'" src="pic/trans.gif" />  </a>'.$totalsm.'  ';
            }
            echo '<a href="messages.php">'.$inboxpic.'</a> '.($messages ? $messages.' ('.$unread.$context->lang['text_message_new'].')' : '0');
            echo '  <a href="messages.php?action=viewmailbox&amp;box=-1"><img class="sentbox" alt="sentbox" title="'.$context->lang['title_sentbox'].'" src="pic/trans.gif" /></a> '.($outmessages ? $outmessages : '0');
            ?>

	</span></td>
	</tr></table></td>
</tr></table>

</td></tr>

<tr><td id="outer" align="center" class="outer" style="padding-top: 20px; padding-bottom: 20px">
<?php
            if ($msgalert) {
                $timeline = TorrentState::resolveTimeline();
                $currentPromotion = $timeline['current'] ?? null;
                $upcomingPromotion = $timeline['upcoming'] ?? null;
                $remarkTpl = $context->lang['full_site_promotion_remark'] ?? 'Remark: %s';
                if ($currentPromotion) {
                    $promotionText = TorrentPromotion::fromIntSafe((int) ($currentPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
                    $msg = sprintf($context->lang['full_site_promotion_in_effect'], $promotionText);
                    if (! empty($currentPromotion['begin']) || ! empty($currentPromotion['deadline'])) {
                        $timeRange = sprintf($context->lang['full_site_promotion_time_range'], $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>'.$timeRange;
                    }
                    if (! empty($currentPromotion['remark'])) {
                        $msg .= '<br/>'.sprintf($remarkTpl, $currentPromotion['remark']);
                    }
                    Html::messageAlertVoid('torrents.php', $msg, 'green');
                }
                if ($upcomingPromotion) {
                    $promotionText = TorrentPromotion::fromIntSafe((int) ($upcomingPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
                    $msg = sprintf($context->lang['full_site_promotion_upcoming'] ?? 'Upcoming full site [%s]', $promotionText);
                    if (! empty($upcomingPromotion['begin']) || ! empty($upcomingPromotion['deadline'])) {
                        $timeRange = sprintf($context->lang['full_site_promotion_time_range'], $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
                        $msg .= '<br/>'.$timeRange;
                    }
                    if (! empty($upcomingPromotion['remark'])) {
                        $msg .= '<br/>'.sprintf($remarkTpl, $upcomingPromotion['remark']);
                    }
                    Html::messageAlertVoid('torrents.php', $msg, 'blue');
                }
                if ($context->user['leechwarn']) {
                    $kicktimeout = Time::format($context->user['leechwarnuntil'], false, false, true);
                    $text = $context->lang['text_please_improve_ratio_within'].$kicktimeout.$context->lang['text_or_you_will_be_banned'];
                    Html::messageAlertVoid('faq.php#id17', $text, 'orange');
                }
                if ($context->deleteNotTransferTwoAccount) {
                    if ($context->user['downloaded'] == 0 && ($context->user['uploaded'] == 0 || $context->user['uploaded'] == $context->iniUploadMain)) {
                        $context->neverDeleteAccount = $context->neverDeleteAccount <= $context->vipClass ? $context->neverDeleteAccount : $context->vipClass;
                        if ($context->userClass() < $context->neverDeleteAccount) {
                            $secs = $context->deleteNotTransferTwoAccount * 24 * 60 * 60;
                            $addedtime = strtotime($context->user['added']);
                            if ($addedtime + $secs / 3 < TIMENOW) {
                                $kicktimeout = Time::format(date('Y-m-d H:i:s', $addedtime + $secs), false, false, true);
                                $text = $context->lang['text_please_download_something_within'].$kicktimeout.$context->lang['text_inactive_account_be_deleted'];
                                Html::messageAlertVoid('rules.php', $text, 'gray');
                            }
                        }
                    }
                }
                if ($context->user['showclienterror']) {
                    $text = $context->lang['text_banned_client_warning'];
                    Html::messageAlertVoid('faq.php#id29', $text, 'black');
                }
                if ($unread) {
                    $text = $context->lang['text_you_have'].$unread.$context->lang['text_new_message'].Strings::addS((int) $unread).$context->lang['text_click_here_to_read'];
                    Html::messageAlertVoid('messages.php', $text, 'red');
                }
                MsgAlert::getInstance()->render();
                $settings_script_name = $context->scriptFileName;
                if (! preg_match('/index/i', $settings_script_name)) {
                    $new_news = $context->cache?->get_value('user_'.$context->user['id'].'_unread_news_count');
                    if ($new_news == '') {
                        $lastHome = $context->user['last_home'] ?? null;
                        $new_news = app(PageLayoutRepository::class)->getUnreadNewsCount($lastHome);
                        $context->cache?->cache_value('user_'.$context->user['id'].'_unread_news_count', $new_news, 300);
                    }
                    if ($new_news > 0) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($new_news).$new_news.$context->lang['text_new_news'];
                        Html::messageAlertVoid('index.php', $text, 'green');
                    }
                }
                // Staff message, not only staff member
                $nummessages = app(MessageRepository::class)->getStaffMessageCountCache($context->user['id'], 'new');
                if ($nummessages === false) {
                    $nummessages = app(MessageRepository::class)->countStaffMessage($context->user['id'], 0);
                    app(MessageRepository::class)->updateStaffMessageCountCache($context->user['id'], 'new', $nummessages);
                }
                if ($nummessages > 0) {
                    $text = $context->lang['text_there_is'].Strings::isOrAre($nummessages).$nummessages.$context->lang['text_new_staff_message'].Strings::addS($nummessages);
                    Html::messageAlertVoid('staffbox.php', $text, 'blue');
                }
                // torrent approval
                if (Permissions::userCan('torrent-approval', false, (int) ($context->user['id'] ?? 0)) && Settings::get('torrent.approval_status_none_visible') == 'no') {
                    $cacheKey = 'TORRENT_APPROVAL_NONE';
                    $toApprovalCounts = $context->cache?->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = app(PageLayoutRepository::class)->getTorrentApprovalNoneCount();
                        $context->cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    if ($toApprovalCounts) {
                        Html::messageAlertVoid('torrents.php?approval_status=0&incldead=0', sprintf($context->lang['text_torrent_to_approval'], Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)), 'darkred');
                    }
                }
                if (Permissions::userCan('staffmem', false, (int) ($context->user['id'] ?? 0))) {
                    if (($complaints = $context->cache?->get_value('COMPLAINTS_COUNT_CACHE')) === false) {
                        $complaints = app(PageLayoutRepository::class)->getOpenComplaintsCount();
                        $context->cache->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
                    }
                    if ($complaints) {
                        Html::messageAlertVoid('complains.php?action=list', sprintf($context->lang['text_complains'], Strings::isOrAre($complaints), $complaints, Strings::addS($complaints)), 'darkred');
                    }
                    $numreports = $context->cache?->get_value('staff_new_report_count');
                    if ($numreports == '') {
                        $numreports = app(PageLayoutRepository::class)->getOpenReportsCount();
                        $context->cache?->cache_value('staff_new_report_count', $numreports, 900);
                    }
                    if ($numreports) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($numreports).$numreports.$context->lang['text_new_report'].Strings::addS($numreports);
                        Html::messageAlertVoid('reports.php', $text, 'blue');
                    }
                    $numcheaters = $context->cache?->get_value('staff_new_cheater_count');
                    if ($numcheaters == '') {
                        $numcheaters = app(PageLayoutRepository::class)->getOpenCheatersCount();
                        $context->cache?->cache_value('staff_new_cheater_count', $numcheaters, 900);
                    }
                    if ($numcheaters) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($numcheaters).$numcheaters.$context->lang['text_new_suspected_cheater'].Strings::addS($numcheaters);
                        Html::messageAlertVoid('cheaterbox.php', $text, 'blue');
                    }
                }
                // show the exam info
                $exam = new Exam;
                $currentExam = $exam->getCurrent($context->user['id']);
                if (! empty($currentExam['html']) && $currentExam['exam'] !== null) {
                    Html::messageAlertVoid($currentExam['exam']->type == ExamType::TASK->value ? 'task.php' : 'messages.php', $currentExam['html'], $currentExam['exam']->background_color ?? 'blue');
                }
            }
            if ($context->offlineMsg) {
                echo "<p><table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='padding: 10px; background: red' class=\"text\" align=\"center\">\n";
                echo '<font color="white">'.$context->lang['text_website_offline_warning'].'</font>';
                echo "</td></tr></table></p><br />\n";
            }
        }
    }

    public static function footer(): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $cspNonce = (string) (request()->attributes->get('csp_nonce', ''));

        echo '</td></tr></table>';
        echo '<div id="footer">';
        echo '<div style="margin-top: 10px; margin-bottom: 30px;" align="center">';
        // Variables for End Time
        $tend = microtime(true);
        $totaltime = $tend - RequestContext::instance()->getStartTimestamp();
        $year = substr($context->dateFounded, 0, 4);
        $yearfounded = $year ? $year : 2007;
        echo ' (c) '.' <a href="'.Http::protocolPrefix(Url::isSecure()).$context->baseUrl.'" target="_self">'.$context->siteName.'</a> '.($context->icpLicenseMain ? ' '.$context->icpLicenseMain.' ' : '').(date('Y') != $yearfounded ? $yearfounded.'-' : '').date('Y').' '.VERSION.'<br /><br />';
        printf('[page created in <b> %s </b> sec', sprintf('%.3f', $totaltime));
        $debugQuery = $context->enableSqlDebugTweak == 'yes' && $context->userClass() >= $context->sqlDebugTweak;
        if ($debugQuery) {
            $query_name_laravel = LegacyDb::lastQuery(true, 'json');
            $dbQueryCount = count($context->queryName) + count($query_name_laravel);
        } else {
            $query_name_laravel = [];
            $dbQueryCount = count($context->queryName) + LegacyDb::lastQuery('COUNT', 'json');
        }
        echo ' with <b>'.$dbQueryCount.'</b> db queries, <b>'.$context->cache?->getCacheReadTimes().'</b> reads and <b>'.$context->cache?->getCacheWriteTimes().'</b> writes of Redis and <b>'.Format::size(memory_get_usage()).'</b> ram]';
        echo "</div>\n";
        if ($debugQuery) {
            echo "<div id=\"sql_debug\" style='text-align: left;'>SQL query list: <ul>";
            foreach ($context->queryName as $query) {
                echo sprintf('<li>%s [%s]</li>', htmlspecialchars($query['query']), $query['time']);
            }
            foreach ($query_name_laravel as $query) {
                echo sprintf('<li>%s [%s ms]</li>', htmlspecialchars($query['raw_query']), $query['time']);
            }
            echo '</ul>';
            echo 'Redis key read: <ul>';
            foreach (($context->cache?->getKeyHits('read') ?? []) as $keyName => $hits) {
                echo '<li>'.htmlspecialchars($keyName).' : '.$hits.'</li>';
            }
            echo '</ul>';
            echo 'Redis key write: <ul>';
            foreach (($context->cache?->getKeyHits('write') ?? []) as $keyName => $hits) {
                echo '<li>'.htmlspecialchars($keyName).' : '.$hits.'</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        if ($context->addKeyShortcut != '') {
            $keyShortcut = $context->addKeyShortcut;
            if ($cspNonce !== '') {
                $keyShortcut = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $keyShortcut);
            }
            echo $keyShortcut;
        }
        echo '</div>';
        if ($context->analyticsCodeTweak) {
            $analyticsCode = $context->analyticsCodeTweak;
            if ($cspNonce !== '') {
                // Inject CSP nonce into <script> tags within analytics code.
                $analyticsCode = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $analyticsCode);
            }
            echo "\n".$analyticsCode."\n";
        }
        foreach (AssetAppender::getAppendFooters() as $value) {
            echo $value;
        }
        $js = <<<'JS'
        <script type="application/javascript" src="js/ajax.js"></script>
        <script type="application/javascript" src="js/nexus.js"></script>
        <script type="application/javascript" src="js/csrf.js"></script>
        <script type="application/javascript" src="js/medium-zoom.min.js"></script>
        <script type="application/javascript" src="js/goup.js"></script>
        JS;
        if ($cspNonce !== '') {
            $js .= "<script nonce=\"{$cspNonce}\">\n";
        } else {
            $js .= "<script>\n";
        }
        $js .= <<<'JS'
        document.addEventListener('DOMContentLoaded', function(){
            mediumZoom('[data-zoomable]')
        });
        </script>
        JS;
        echo $js;
        echo '<img id="nexus-preview" style="display: none; position: absolute" src="" />';
        echo '</body></html>';
    }
}
