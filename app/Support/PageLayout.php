<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Enums\ExamType;
use App\Enums\TorrentPromotion;
use App\Models\HitAndRun;
use App\Models\TorrentState;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\HitAndRunRepository;
use App\Repositories\StaffMessageRepository;
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

        self::renderHeader($context, $title, $msgalert, $script, $place, $cspNonce);
    }

    private static function renderHeader(PageLayoutContext $context, string $title, bool $msgalert, string $script, string $place, string $cspNonce): void
    {
        $context->cache?->setLanguage($context->langDir);
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

        $addiCode = Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        if ($cspNonce !== '' && $addiCode !== '') {
            $addiCode = (string) preg_replace('/<style(?![^>]*\snonce=)/i', '<style nonce="'.$cspNonce.'"', $addiCode);
        }
        $cssUri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        $cssUpdateDate = $context->cssDateTweak ? '?'.htmlspecialchars($context->cssDateTweak) : '';
        $locale = str_replace('_', '-', app()->getLocale());
        $fontCssUri = Style::fontCssUri($context->userFontSize());
        $forumPicFolder = Forum::picFolder($context->langDir);
        $appendHeaders = AssetAppender::getAppendHeaders();
        $contentWidth = defined('CONTENT_WIDTH') ? (int) \constant('CONTENT_WIDTH') : 0;
        $headTableWidth = $context->user !== null ? $contentWidth + 28.66 : $contentWidth;

        $searchBoxIcons = [];
        if ($context->user) {
            $requireSearchBoxIdAr = SearchBox::requiredIds();
            if (! empty($requireSearchBoxIdAr)) {
                $icons = app(SearchBoxRepositoryInterface::class)->listIcon($requireSearchBoxIdAr);
                foreach ($icons as $icon) {
                    $searchBoxIcons[] = trim($icon['cssfile'] ?? '', '/');
                }
            }
        }

        $user = $context->user;
        $lang = $context->lang;
        $menuHtml = '';
        $username = '';
        $isModerator = false;
        $isSysop = false;
        $seedbonus = '';
        $attendanceLink = '';
        $medalLabel = '';
        $taskLabel = '';
        $userId = 0;
        $invites = '';
        $pendingInviteCount = 0;
        $managementSystemLink = '';
        $ratio = '';
        $uploaded = '';
        $downloaded = '';
        $activeseed = '';
        $activeleech = '';
        $connectable = '';
        $slotsDisplay = '';
        $hitAndRunEnabled = false;
        $hitAndRunStatus = '';
        $globalSearchEnabled = false;
        $searchFormTarget = '_blank';
        $requestSearchEscaped = '';
        $searchKeywordPlaceholder = '';
        $searchBoxAreaSelect = '';
        $globalSearchLabel = '';
        $staffIcons = '';
        $messageAlerts = '';
        $offlineMsg = $context->offlineMsg;
        $offlineMsgHtml = '';

        if ($context->user) {
            $menuHtml = Frame::mainOpen('', false, 100, $contentWidth).$context->menuHtml.Frame::CLOSE;

            $ratio = (string) Ratio::forUserId($context->user['id']);
            $messages = $context->cache?->get_value('user_'.$context->user['id'].'_inbox_count');
            if ($messages == '') {
                $messages = app(PageLayoutRepositoryInterface::class)->getInboxCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_inbox_count', $messages, 900);
            }
            $outmessages = $context->cache?->get_value('user_'.$context->user['id'].'_outbox_count');
            if ($outmessages == '') {
                $outmessages = app(PageLayoutRepositoryInterface::class)->getOutboxCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_outbox_count', $outmessages, 900);
            }
            $connect = $context->cache?->get_value('user_'.$context->user['id'].'_connect');
            if ($connect === false || $connect === null) {
                $connect = app(PageLayoutRepositoryInterface::class)->getConnectable((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_connect', $connect, 900);
            }
            if ($connect === 1) {
                $connectable = '<b><font color="green">'.$context->lang['text_yes'].'</font></b>';
            } elseif ($connect === 0) {
                $connectable = '<a href="faq.php#id21"><b><font color="red">'.$context->lang['text_no'].'</font></b></a>';
            } else {
                $connectable = $context->lang['text_unknown'];
            }
            $activeseed = $context->cache?->get_value('user_'.$context->user['id'].'_active_seed_count');
            if ($activeseed == '') {
                $activeseed = app(PageLayoutRepositoryInterface::class)->getActiveSeedCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_active_seed_count', $activeseed, 60);
            }
            $activeleech = $context->cache?->get_value('user_'.$context->user['id'].'_active_leech_count');
            if ($activeleech == '') {
                $activeleech = app(PageLayoutRepositoryInterface::class)->getActiveLeechCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_active_leech_count', $activeleech, 60);
            }
            $unread = $context->cache?->get_value('user_'.$context->user['id'].'_unread_message_count');
            if ($unread == '') {
                $unread = app(PageLayoutRepositoryInterface::class)->getUnreadMessageCount((int) $context->user['id']);
                $context->cache?->cache_value('user_'.$context->user['id'].'_unread_message_count', $unread, 60);
            }
            $inboxpic = '<img class="'.($unread ? 'inboxnew' : 'inbox').'" src="pic/trans.gif" alt="inbox" title="'.($unread ? $context->lang['title_inbox_new_messages'] : $context->lang['title_inbox_no_new_messages']).'" />';

            $username = UserDisplay::username($context->user['id']);
            $isModerator = $context->userClass() >= $context->moderatorClass;
            $isSysop = $context->userClass() >= $context->sysopClass;
            $seedbonus = number_format((float) ($context->user['seedbonus'] ?? 0), 1);

            $attendanceRep = app(AttendanceRepository::class);
            $attendance = $attendanceRep->getAttendance($context->user['id'], date('Ymd'));
            if ($attendance) {
                $attendanceLink = sprintf(' <a href="attendance.php" class="">'.$context->lang['text_attended'].'</a>', $attendance->points, $context->user['attendance_card']);
            } else {
                $attendanceLink = sprintf(' <a href="attendance.php" class="faqlink">%s</a>', $context->lang['text_attendance']);
            }

            $medalLabel = Locale::trans('medal.label');
            $taskLabel = Locale::trans('exam.type_task');
            $userId = $context->user['id'];
            $invites = $context->user['invites'];
            $pendingInviteCount = app(PageLayoutRepositoryInterface::class)->getPendingInviteCount((int) $context->user['id']);

            if ($context->userClass() >= User::getAccessAdminClassMin()) {
                $managementSystemLink = sprintf('[<a href="%s" target="_blank">%s</a>]', Env::get('FILAMENT_PATH', 'nexusphp'), $context->lang['text_management_system']);
            }

            $uploaded = Format::size((int) ($context->user['uploaded'] ?? 0));
            $downloaded = Format::size((int) ($context->user['downloaded'] ?? 0));
            $slotsDisplay = Slots::display((int) $context->user['uploaded'], (int) $context->user['downloaded'], $context->maxdlSystem, $context->userClass(), $context->vipClass, $context->lang['text_slots'] ?? '', $context->lang['text_unlimited'] ?? '');

            $hitAndRunEnabled = HitAndRun::getIsEnabled();
            if ($hitAndRunEnabled) {
                $hitAndRunStatus = sprintf('[<a href="myhr.php">%s</a>]', app(HitAndRunRepository::class)->getStatusStats($context->user['id']));
            }

            $globalSearchEnabled = Settings::get('main.enable_global_search') == 'yes';
            $searchFormTarget = RequestContext::instance()->getScript() == 'search' ? '_self' : '_blank';
            $requestSearchEscaped = Html::escapeAttr((string) ($context->requestSearch ?? ''));
            $searchKeywordPlaceholder = Locale::trans('search.search_keyword');
            $searchBoxAreaSelect = SearchBox::areaSelect($context->requestSearchArea ?? '', ['style' => 'width: 88px']);
            $globalSearchLabel = Locale::trans('search.global_search');

            $staffIcons = '';
            if (Permissions::userCan('staffmem', false, (int) ($context->user['id'] ?? 0))) {
                $totalreports = $context->cache?->get_value('staff_report_count');
                if ($totalreports == '') {
                    $totalreports = app(PageLayoutRepositoryInterface::class)->getTotalReports();
                    $context->cache?->cache_value('staff_report_count', $totalreports, 900);
                }
                $totalcheaters = $context->cache?->get_value('staff_cheater_count');
                if ($totalcheaters == '') {
                    $totalcheaters = app(PageLayoutRepositoryInterface::class)->getTotalCheaters();
                    $context->cache?->cache_value('staff_cheater_count', $totalcheaters, 900);
                }
                $staffIcons .= '<a href="cheaterbox.php"><img class="cheaterbox" alt="cheaterbox" title="'.$context->lang['title_cheaterbox'].'" src="pic/trans.gif" />  </a>'.$totalcheaters.'  <a href="reports.php"><img class="reportbox" alt="reportbox" title="'.$context->lang['title_reportbox'].'" src="pic/trans.gif" />  </a>'.$totalreports;
            }
            $staffIcons .= ' <a href="friends.php"><img class="buddylist" alt="Buddylist" title="'.$context->lang['title_buddylist'].'" src="pic/trans.gif" /></a>';
            $staffIcons .= ' <a href="getrss.php"><img class="rss" alt="RSS" title="'.$context->lang['title_get_rss'].'" src="pic/trans.gif" /></a>';
            $staffIcons .= '<br/>';
            $totalsm = app(StaffMessageRepository::class)->getStaffMessageCountCache($context->user['id'], 'total');
            if ($totalsm === false) {
                $totalsm = app(StaffMessageRepository::class)->countStaffMessage($context->user['id']);
                app(StaffMessageRepository::class)->updateStaffMessageCountCache($context->user['id'], 'total', $totalsm);
            }
            if ($totalsm > 0) {
                $staffIcons .= '  <a href="staffbox.php"><img class="staffbox" alt="staffbox" title="'.$context->lang['title_staffbox'].'" src="pic/trans.gif" />  </a>'.$totalsm.'  ';
            }
            $staffIcons .= '<a href="messages.php">'.$inboxpic.'</a> '.($messages ? $messages.' ('.$unread.$context->lang['text_message_new'].')' : '0');
            $staffIcons .= '  <a href="messages.php?action=viewmailbox&amp;box=-1"><img class="sentbox" alt="sentbox" title="'.$context->lang['title_sentbox'].'" src="pic/trans.gif" /></a> '.($outmessages ? $outmessages : '0');

            ob_start();
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
                        $new_news = app(PageLayoutRepositoryInterface::class)->getUnreadNewsCount($lastHome);
                        $context->cache?->cache_value('user_'.$context->user['id'].'_unread_news_count', $new_news, 300);
                    }
                    $new_news = (int) $new_news;
                    if ($new_news > 0) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($new_news).$new_news.$context->lang['text_new_news'];
                        Html::messageAlertVoid('index.php', $text, 'green');
                    }
                }
                $nummessages = app(StaffMessageRepository::class)->getStaffMessageCountCache($context->user['id'], 'new');
                if ($nummessages === false) {
                    $nummessages = app(StaffMessageRepository::class)->countStaffMessage($context->user['id'], 0);
                    app(StaffMessageRepository::class)->updateStaffMessageCountCache($context->user['id'], 'new', $nummessages);
                }
                $nummessages = (int) $nummessages;
                if ($nummessages > 0) {
                    $text = $context->lang['text_there_is'].Strings::isOrAre($nummessages).$nummessages.$context->lang['text_new_staff_message'].Strings::addS($nummessages);
                    Html::messageAlertVoid('staffbox.php', $text, 'blue');
                }
                if (Permissions::userCan('torrent-approval', false, (int) ($context->user['id'] ?? 0)) && Settings::get('torrent.approval_status_none_visible') == 'no') {
                    $cacheKey = 'TORRENT_APPROVAL_NONE';
                    $toApprovalCounts = $context->cache?->get_value($cacheKey);
                    if ($toApprovalCounts === false) {
                        $toApprovalCounts = app(PageLayoutRepositoryInterface::class)->getTorrentApprovalNoneCount();
                        $context->cache->cache_value($cacheKey, $toApprovalCounts, 60);
                    }
                    $toApprovalCounts = (int) $toApprovalCounts;
                    if ($toApprovalCounts) {
                        Html::messageAlertVoid('torrents.php?approval_status=0&incldead=0', sprintf($context->lang['text_torrent_to_approval'], Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)), 'darkred');
                    }
                }
                if (Permissions::userCan('staffmem', false, (int) ($context->user['id'] ?? 0))) {
                    if (($complaints = $context->cache?->get_value('COMPLAINTS_COUNT_CACHE')) === false) {
                        $complaints = app(PageLayoutRepositoryInterface::class)->getOpenComplaintsCount();
                        $context->cache->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
                    }
                    $complaints = (int) $complaints;
                    if ($complaints) {
                        Html::messageAlertVoid('complains.php?action=list', sprintf($context->lang['text_complains'], Strings::isOrAre($complaints), $complaints, Strings::addS($complaints)), 'darkred');
                    }
                    $numreports = $context->cache?->get_value('staff_new_report_count');
                    if ($numreports == '') {
                        $numreports = app(PageLayoutRepositoryInterface::class)->getOpenReportsCount();
                        $context->cache?->cache_value('staff_new_report_count', $numreports, 900);
                    }
                    $numreports = (int) $numreports;
                    if ($numreports) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($numreports).$numreports.$context->lang['text_new_report'].Strings::addS($numreports);
                        Html::messageAlertVoid('reports.php', $text, 'blue');
                    }
                    $numcheaters = $context->cache?->get_value('staff_new_cheater_count');
                    if ($numcheaters == '') {
                        $numcheaters = app(PageLayoutRepositoryInterface::class)->getOpenCheatersCount();
                        $context->cache?->cache_value('staff_new_cheater_count', $numcheaters, 900);
                    }
                    $numcheaters = (int) $numcheaters;
                    if ($numcheaters) {
                        $text = $context->lang['text_there_is'].Strings::isOrAre($numcheaters).$numcheaters.$context->lang['text_new_suspected_cheater'].Strings::addS($numcheaters);
                        Html::messageAlertVoid('cheaterbox.php', $text, 'blue');
                    }
                }
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
            $messageAlerts = (string) ob_get_clean();
        }

        echo view('layouts.legacy.header', [
            'cspNonce' => $cspNonce,
            'title' => $title,
            'locale' => $locale,
            'projectName' => PROJECTNAME,
            'csrfToken' => csrf_token(),
            'metaKeywords' => $context->metaKeywordsTweak,
            'metaDescription' => $context->metaDescriptionTweak,
            'addiCode' => $addiCode,
            'cssUri' => $cssUri,
            'cssUpdateDate' => $cssUpdateDate,
            'fontCssUri' => $fontCssUri,
            'forumPicFolder' => $forumPicFolder,
            'siteName' => $context->siteName,
            'slogan' => $context->slogan,
            'logoMain' => $context->logoMain,
            'enableDonation' => $context->enableDonation,
            'appendHeaders' => $appendHeaders,
            'contentWidth' => $contentWidth,
            'headTableWidth' => $headTableWidth,
            'searchBoxIcons' => $searchBoxIcons,
            'user' => $user,
            'lang' => $lang,
            'menuHtml' => $menuHtml,
            'username' => $username,
            'isModerator' => $isModerator,
            'isSysop' => $isSysop,
            'seedbonus' => $seedbonus,
            'attendanceLink' => $attendanceLink,
            'medalLabel' => $medalLabel,
            'taskLabel' => $taskLabel,
            'userId' => $userId,
            'invites' => $invites,
            'pendingInviteCount' => $pendingInviteCount,
            'managementSystemLink' => $managementSystemLink,
            'ratio' => $ratio,
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'activeseed' => $activeseed,
            'activeleech' => $activeleech,
            'connectable' => $connectable,
            'slotsDisplay' => $slotsDisplay,
            'hitAndRunEnabled' => $hitAndRunEnabled,
            'hitAndRunStatus' => $hitAndRunStatus,
            'globalSearchEnabled' => $globalSearchEnabled,
            'searchFormTarget' => $searchFormTarget,
            'requestSearchEscaped' => $requestSearchEscaped,
            'searchKeywordPlaceholder' => $searchKeywordPlaceholder,
            'searchBoxAreaSelect' => $searchBoxAreaSelect,
            'globalSearchLabel' => $globalSearchLabel,
            'staffIcons' => $staffIcons,
            'messageAlerts' => $messageAlerts,
            'offlineMsg' => $offlineMsg,
            'offlineMsgHtml' => $offlineMsgHtml,
        ])->render();
    }

    public static function footer(): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $cspNonce = (string) (request()->attributes->get('csp_nonce', ''));

        $tend = microtime(true);
        $totaltime = $tend - RequestContext::instance()->getStartTimestamp();
        $year = substr($context->dateFounded, 0, 4);
        $yearfounded = $year ? $year : 2007;
        $copyrightHtml = ' (c) '.' <a href="'.Http::protocolPrefix(Url::isSecure()).$context->baseUrl.'" target="_self">'.$context->siteName.'</a> '.($context->icpLicenseMain ? ' '.$context->icpLicenseMain.' ' : '').(date('Y') != $yearfounded ? $yearfounded.'-' : '').date('Y').' '.VERSION.'<br /><br />';

        $debugQuery = $context->enableSqlDebugTweak == 'yes' && $context->userClass() >= $context->sqlDebugTweak;
        if ($debugQuery) {
            $query_name_laravel = LegacyDb::lastQuery(true, 'json');
            $dbQueryCount = count($context->queryName) + count($query_name_laravel);
        } else {
            $query_name_laravel = [];
            $dbQueryCount = count($context->queryName) + LegacyDb::lastQuery('COUNT', 'json');
        }
        $cacheReadTimes = $context->cache?->getCacheReadTimes();
        $cacheWriteTimes = $context->cache?->getCacheWriteTimes();
        $pageStatsLine = sprintf('[page created in <b> %s </b> sec', sprintf('%.3f', $totaltime)).' with <b>'.$dbQueryCount.'</b> db queries, <b>'.$cacheReadTimes.'</b> reads and <b>'.$cacheWriteTimes.'</b> writes of Redis and <b>'.Format::size(memory_get_usage()).'</b> ram]';

        $debugQueryHtml = '';
        if ($debugQuery) {
            $debugQueryHtml = "<div id=\"sql_debug\" style='text-align: left;'>SQL query list: <ul>";
            foreach ($context->queryName as $query) {
                $debugQueryHtml .= sprintf('<li>%s [%s]</li>', htmlspecialchars($query['query']), $query['time']);
            }
            foreach ($query_name_laravel as $query) {
                $debugQueryHtml .= sprintf('<li>%s [%s ms]</li>', htmlspecialchars($query['raw_query']), $query['time']);
            }
            $debugQueryHtml .= '</ul>';
            $debugQueryHtml .= 'Redis key read: <ul>';
            foreach (($context->cache?->getKeyHits('read') ?? []) as $keyName => $hits) {
                $debugQueryHtml .= '<li>'.htmlspecialchars((string) $keyName).' : '.$hits.'</li>';
            }
            $debugQueryHtml .= '</ul>';
            $debugQueryHtml .= 'Redis key write: <ul>';
            foreach (($context->cache?->getKeyHits('write') ?? []) as $keyName => $hits) {
                $debugQueryHtml .= '<li>'.htmlspecialchars((string) $keyName).' : '.$hits.'</li>';
            }
            $debugQueryHtml .= '</ul>';
            $debugQueryHtml .= '</div>';
        }

        $keyShortcut = '';
        if ($context->addKeyShortcut != '') {
            $keyShortcut = $context->addKeyShortcut;
            if ($cspNonce !== '') {
                $keyShortcut = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $keyShortcut);
            }
        }

        $analyticsCode = '';
        if ($context->analyticsCodeTweak) {
            $analyticsCode = $context->analyticsCodeTweak;
            if ($cspNonce !== '') {
                $analyticsCode = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $analyticsCode);
            }
            $analyticsCode = "\n".$analyticsCode."\n";
        }

        $appendFooters = AssetAppender::getAppendFooters();

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

        echo view('layouts.legacy.footer', [
            'copyrightHtml' => $copyrightHtml,
            'pageStatsLine' => $pageStatsLine,
            'debugQuery' => $debugQuery,
            'debugQueryHtml' => $debugQueryHtml,
            'keyShortcut' => $keyShortcut,
            'analyticsCode' => $analyticsCode,
            'appendFooters' => $appendFooters,
            'jsBlock' => $js,
        ])->render();
    }
}
