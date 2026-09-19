<?php

declare(strict_types=1);

namespace App\ViewModels;

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
use App\Support\Cache\LegacyRedisCache;
use App\Support\Env;
use App\Support\Exam;
use App\Support\Format;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\PageLayoutContext;
use App\Support\Permissions;
use App\Support\Ratio;
use App\Support\RequestContext;
use App\Support\SearchBox;
use App\Support\Settings;
use App\Support\Slots;
use App\Support\Strings;
use App\Support\Style;
use App\Support\Time;
use App\Support\UserDisplay;
use App\Utils\MsgAlert;

/**
 * Data for the shared page chrome (`layouts.partials.*`, ADR 0018).
 *
 * Variant A (ADR 0014) gave `layouts/modern` a semantic HTML5 shell; ADR 0018
 * reuses the same partials for legacy pages rendered through
 * `PageLayout::headerHtml()`/`footerHtml()`. This view model gathers every
 * field both variants need — user bar, nav, global search, staff icons,
 * message alerts, offline banner and footer stats — as plain data;
 * markup lives in the partials. `variant` selects the chrome flavour
 * (legacy keeps its theme stylesheets and head-loaded scripts until page
 * bodies migrate in stage 3).
 */
final class SiteChromeViewModel
{
    /**
     * @param  array<string, mixed>|null  $user
     * @param  list<array{key: string, href: string, label: string, selected: bool, attrs: string}>  $navItems
     * @param  list<string>  $headStyles
     * @param  list<string>  $headScripts
     * @param  list<string>  $footScripts
     * @param  list<array{value: int, label: string, selected: bool}>  $searchAreas
     * @param  list<array{url: string, text: SafeHtml, color: string}>  $alerts
     * @param  list<array{query: string, time: string}>  $debugQueries
     * @param  list<array{query: string, time: string}>  $debugLaravelQueries
     * @param  array<string, int>  $debugRedisReads
     * @param  array<string, int>  $debugRedisWrites
     */
    private function __construct(
        public readonly string $siteName,
        public readonly string $slogan,
        public readonly string $logoMain,
        public readonly string $baseUrl,
        public readonly string $title,
        public readonly ?array $user,
        public readonly array $navItems,
        public readonly SafeHtml $usernameHtml,
        public readonly string $ratio,
        public readonly string $uploaded,
        public readonly string $downloaded,
        public readonly string $seedbonus,
        public readonly int $inboxCount,
        public readonly int $outboxCount,
        public readonly int $unreadCount,
        public readonly int $activeSeed,
        public readonly int $activeLeech,
        public readonly string $invites,
        public readonly int $pendingInvites,
        public readonly bool $isModerator,
        public readonly bool $isSysop,
        public readonly bool $globalSearchEnabled,
        public readonly string $requestSearch,
        public readonly string $yearFounded,
        public readonly string $variant,
        public readonly string $locale,
        public readonly string $cspNonce,
        public readonly string $metaKeywords,
        public readonly string $metaDescription,
        public readonly array $headStyles,
        public readonly array $headScripts,
        public readonly array $footScripts,
        public readonly SafeHtml $inlineHeadHtml,
        public readonly bool $attendanceDone,
        public readonly int $attendancePoints,
        public readonly int $attendanceCard,
        public readonly string $medalLabel,
        public readonly string $taskLabel,
        public readonly string $managementHref,
        public readonly ?bool $connectable,
        public readonly int $maxSlots,
        public readonly bool $hitAndRunEnabled,
        public readonly SafeHtml $hitAndRunStatsHtml,
        public readonly string $searchFormTarget,
        public readonly string $searchKeywordPlaceholder,
        public readonly string $globalSearchLabel,
        public readonly array $searchAreas,
        public readonly bool $canStaffmem,
        public readonly int $cheaterCount,
        public readonly int $reportCount,
        public readonly int $staffMessageTotal,
        public readonly array $alerts,
        public readonly bool $offlineMsg,
        public readonly SafeHtml $offlineMsgHtml,
        public readonly bool $enableDonation,
        public readonly string $picFolder,
        public readonly string $icpLicense,
        public readonly SafeHtml $versionHtml,
        public readonly string $statsTime,
        public readonly int $statsDbQueries,
        public readonly int $statsCacheReads,
        public readonly int $statsCacheWrites,
        public readonly string $statsRam,
        public readonly bool $debugEnabled,
        public readonly array $debugQueries,
        public readonly array $debugLaravelQueries,
        public readonly array $debugRedisReads,
        public readonly array $debugRedisWrites,
        public readonly SafeHtml $keyShortcutHtml,
        public readonly SafeHtml $analyticsHtml,
    ) {}

    public static function load(
        string $title,
        PageLayoutRepositoryInterface $repo,
        ?PageLayoutContext $context = null,
        string $variant = 'modern',
        bool $msgalert = true,
        bool $skipUserData = false,
    ): self {
        $context ??= PageLayoutContext::fromSupportContext();
        $user = $context->user;
        $cache = $context->cache;
        $cspNonce = (string) (request()->attributes->get('csp_nonce', ''));

        $navItems = self::navItems($context);
        $usernameHtml = SafeHtml::fromTrustedHtml('');
        $ratio = '';
        $uploaded = '';
        $downloaded = '';
        $seedbonus = '';
        $inboxCount = 0;
        $outboxCount = 0;
        $unreadCount = 0;
        $activeSeed = 0;
        $activeLeech = 0;
        $invites = '';
        $pendingInvites = 0;
        $isModerator = false;
        $isSysop = false;
        $attendanceDone = false;
        $attendancePoints = 0;
        $attendanceCard = 0;
        $medalLabel = '';
        $taskLabel = '';
        $managementHref = '';
        $connectable = null;
        $maxSlots = 0;
        $hitAndRunEnabled = false;
        $hitAndRunStatsHtml = SafeHtml::fromTrustedHtml('');
        $canStaffmem = false;
        $cheaterCount = 0;
        $reportCount = 0;
        $staffMessageTotal = 0;
        $alerts = [];

        if ($user !== null && ! empty($user['id']) && ! $skipUserData) {
            $userId = (int) $user['id'];
            $usernameHtml = UserDisplay::username($userId);
            $ratio = (string) Ratio::forUserId($userId);
            $uploaded = Format::size((int) ($user['uploaded'] ?? 0));
            $downloaded = Format::size((int) ($user['downloaded'] ?? 0));
            $seedbonus = number_format((float) ($user['seedbonus'] ?? 0), 1);
            $invites = (string) ($user['invites'] ?? '0');
            $isModerator = $context->userClass() >= $context->moderatorClass;
            $isSysop = $context->userClass() >= $context->sysopClass;

            $inboxCount = (int) self::cachedCount($cache, 'user_'.$userId.'_inbox_count', fn () => $repo->getInboxCount($userId), 900);
            $outboxCount = (int) self::cachedCount($cache, 'user_'.$userId.'_outbox_count', fn () => $repo->getOutboxCount($userId), 900);
            $unreadCount = (int) self::cachedCount($cache, 'user_'.$userId.'_unread_message_count', fn () => $repo->getUnreadMessageCount($userId), 60);
            $activeSeed = (int) self::cachedCount($cache, 'user_'.$userId.'_active_seed_count', fn () => $repo->getActiveSeedCount($userId), 60);
            $activeLeech = (int) self::cachedCount($cache, 'user_'.$userId.'_active_leech_count', fn () => $repo->getActiveLeechCount($userId), 60);
            $pendingInvites = (int) $repo->getPendingInviteCount($userId);

            $connect = self::cachedCount($cache, 'user_'.$userId.'_connect', fn () => $repo->getConnectable($userId), 900);
            $connectable = $connect === 1 ? true : ($connect === 0 ? false : null);

            $slotsEnabled = $context->maxdlSystem === 'yes' && $context->userClass() < $context->vipClass;
            if ($slotsEnabled) {
                $maxSlots = Slots::maxDownloadSlots((int) $user['uploaded'], (int) $user['downloaded']);
            }

            $attendance = app(AttendanceRepository::class)->getAttendance($userId, date('Ymd'));
            if ($attendance) {
                $attendanceDone = true;
                $attendancePoints = (int) $attendance->points;
                $attendanceCard = (int) ($user['attendance_card'] ?? 0);
            }

            $medalLabel = Locale::trans('medal.label');
            $taskLabel = Locale::trans('exam.type_task');

            if ($context->userClass() >= User::getAccessAdminClassMin()) {
                $managementHref = '/'.ltrim((string) Env::get('FILAMENT_PATH', 'nexusphp'), '/');
            }

            $hitAndRunEnabled = HitAndRun::getIsEnabled();
            if ($hitAndRunEnabled) {
                $hitAndRunStatsHtml = SafeHtml::fromTrustedHtml(
                    (string) app(HitAndRunRepository::class)->getStatusStats($userId)
                );
            }

            $canStaffmem = Permissions::userCan('staffmem', false, $userId);
            if ($canStaffmem) {
                $cheaterCount = (int) self::cachedCount($cache, 'staff_cheater_count', fn () => $repo->getTotalCheaters(), 900);
                $reportCount = (int) self::cachedCount($cache, 'staff_report_count', fn () => $repo->getTotalReports(), 900);
            }
            $staffMessageTotal = app(StaffMessageRepository::class)->getStaffMessageCountCache($userId, 'total');
            if ($staffMessageTotal === false) {
                $staffMessageTotal = app(StaffMessageRepository::class)->countStaffMessage($userId);
                app(StaffMessageRepository::class)->updateStaffMessageCountCache($userId, 'total', $staffMessageTotal);
            }
            $staffMessageTotal = (int) $staffMessageTotal;

            $alerts = self::loadAlerts($context, $userId, (int) $unreadCount, $repo, $msgalert);
        }

        $fullTitle = $title === '' ? $context->siteName : $context->siteName.' :: '.$title;
        if ($context->titleKeywordsTweak !== '') {
            $fullTitle .= ' '.$context->titleKeywordsTweak;
        }
        $fullTitle .= ' - Powered by '.PROJECTNAME;

        [$headStyles, $headScripts, $inlineHeadHtml, $picFolder] = self::headAssets($context, $variant, $cspNonce);

        $searchAreas = [];
        foreach ([0, 1, 3] as $area) {
            $searchAreas[] = [
                'value' => $area,
                'label' => Locale::trans("search.search_area_options.{$area}", [], null),
                'selected' => (int) $context->requestSearchArea === $area,
            ];
        }

        $debugEnabled = $context->enableSqlDebugTweak === 'yes' && $context->userClass() >= $context->sqlDebugTweak;
        $laravelQueries = [];
        if ($debugEnabled) {
            $laravelQueries = (array) LegacyDb::lastQuery(true, 'json');
            $dbQueryCount = count($context->queryName) + count($laravelQueries);
        } else {
            $dbQueryCount = count($context->queryName) + (int) LegacyDb::lastQuery('COUNT', 'json');
        }

        $keyShortcut = '';
        if ($context->addKeyShortcut !== '') {
            $keyShortcut = $context->addKeyShortcut;
            if ($cspNonce !== '') {
                $keyShortcut = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $keyShortcut);
            }
        }

        $analyticsCode = '';
        if ($context->analyticsCodeTweak !== '') {
            $analyticsCode = $context->analyticsCodeTweak;
            if ($cspNonce !== '') {
                $analyticsCode = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $analyticsCode);
            }
            $analyticsCode = "\n".$analyticsCode."\n";
        }

        return new self(
            siteName: $context->siteName,
            slogan: $context->slogan,
            logoMain: $context->logoMain,
            baseUrl: $context->baseUrl,
            title: $fullTitle,
            user: $user,
            navItems: $navItems,
            usernameHtml: $usernameHtml,
            ratio: $ratio,
            uploaded: $uploaded,
            downloaded: $downloaded,
            seedbonus: $seedbonus,
            inboxCount: (int) $inboxCount,
            outboxCount: (int) $outboxCount,
            unreadCount: $unreadCount,
            activeSeed: $activeSeed,
            activeLeech: $activeLeech,
            invites: $invites,
            pendingInvites: $pendingInvites,
            isModerator: $isModerator,
            isSysop: $isSysop,
            globalSearchEnabled: Settings::get('main.enable_global_search') === 'yes',
            requestSearch: $context->requestSearch,
            yearFounded: substr($context->dateFounded, 0, 4) ?: '2007',
            variant: $variant,
            locale: str_replace('_', '-', app()->getLocale()),
            cspNonce: $cspNonce,
            metaKeywords: $context->metaKeywordsTweak,
            metaDescription: $context->metaDescriptionTweak,
            headStyles: $headStyles,
            headScripts: $headScripts,
            footScripts: self::footScripts($variant),
            inlineHeadHtml: $inlineHeadHtml,
            attendanceDone: $attendanceDone,
            attendancePoints: $attendancePoints,
            attendanceCard: $attendanceCard,
            medalLabel: $medalLabel,
            taskLabel: $taskLabel,
            managementHref: $managementHref,
            connectable: $connectable,
            maxSlots: $maxSlots,
            hitAndRunEnabled: $hitAndRunEnabled,
            hitAndRunStatsHtml: $hitAndRunStatsHtml,
            searchFormTarget: RequestContext::instance()->getScript() === 'search' ? '_self' : '_blank',
            searchKeywordPlaceholder: Locale::trans('search.search_keyword'),
            globalSearchLabel: Locale::trans('search.global_search'),
            searchAreas: $searchAreas,
            canStaffmem: $canStaffmem,
            cheaterCount: $cheaterCount,
            reportCount: $reportCount,
            staffMessageTotal: $staffMessageTotal,
            alerts: $alerts,
            offlineMsg: $context->offlineMsg,
            offlineMsgHtml: SafeHtml::fromTrustedHtml((string) (__('legacy/functions.text_website_offline_warning'))),
            enableDonation: $context->enableDonation === 'yes',
            picFolder: $picFolder,
            icpLicense: $context->icpLicenseMain,
            versionHtml: SafeHtml::fromTrustedHtml(defined('VERSION') ? (string) \constant('VERSION') : ''),
            statsTime: sprintf('%.3f', microtime(true) - RequestContext::instance()->getStartTimestamp()),
            statsDbQueries: $dbQueryCount,
            statsCacheReads: (int) ($context->cache?->getCacheReadTimes() ?? 0),
            statsCacheWrites: (int) ($context->cache?->getCacheWriteTimes() ?? 0),
            statsRam: Format::size(memory_get_usage()),
            debugEnabled: $debugEnabled,
            debugQueries: array_values(array_map(
                static fn (array $query): array => ['query' => (string) ($query['query'] ?? ''), 'time' => (string) ($query['time'] ?? '')],
                $context->queryName,
            )),
            debugLaravelQueries: array_values(array_map(
                static fn (array $query): array => ['query' => (string) ($query['raw_query'] ?? ''), 'time' => (string) ($query['time'] ?? '')],
                $laravelQueries,
            )),
            debugRedisReads: $context->cache?->getKeyHits('read') ?? [],
            debugRedisWrites: $context->cache?->getKeyHits('write') ?? [],
            keyShortcutHtml: SafeHtml::fromTrustedHtml($keyShortcut),
            analyticsHtml: SafeHtml::fromTrustedHtml($analyticsCode),
        );
    }

    /**
     * Stylesheets/scripts that only the legacy variant needs on top of the
     * shared chrome assets: the user's theme, font size and forum sprites,
     * plus the `addicode` block keyed to the chosen stylesheet.
     *
     * @return array{0: list<string>, 1: list<string>, 2: SafeHtml, 3: string}
     */
    private static function headAssets(PageLayoutContext $context, string $variant, string $cspNonce): array
    {
        $picFolder = Forum::picFolder($context->langDir);
        if ($variant !== 'legacy') {
            return [
                ['styles/sprites.css', 'styles/nexus.css'],
                [],
                SafeHtml::fromTrustedHtml(''),
                $picFolder,
            ];
        }

        $cssUpdateDate = $context->cssDateTweak !== '' ? '?'.$context->cssDateTweak : '';
        $cssUri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);

        $headStyles = [
            Style::fontCssUri($context->userFontSize()).$cssUpdateDate,
            'styles/sprites.css'.$cssUpdateDate,
            $picFolder.'/forumsprites.css'.$cssUpdateDate,
            $cssUri.'theme.css'.$cssUpdateDate,
            $cssUri.'DomTT.css'.$cssUpdateDate,
            'styles/nexus.css'.$cssUpdateDate,
        ];
        if ($context->user !== null) {
            $requireSearchBoxIds = SearchBox::requiredIds();
            if ($requireSearchBoxIds !== []) {
                foreach (app(SearchBoxRepositoryInterface::class)->listIcon($requireSearchBoxIds) as $icon) {
                    $cssfile = trim((string) ($icon['cssfile'] ?? ''), '/');
                    if ($cssfile !== '') {
                        $headStyles[] = $cssfile.$cssUpdateDate;
                    }
                }
            }
        }

        $addiCode = Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        if ($cspNonce !== '' && $addiCode !== '') {
            $addiCode = (string) preg_replace('/<style(?![^>]*\snonce=)/i', '<style nonce="'.$cspNonce.'"', $addiCode);
        }

        return [
            $headStyles,
            [
                'js/curtain_imageresizer.js'.$cssUpdateDate,
                'js/ajaxbasic.js'.$cssUpdateDate,
                'js/common.js'.$cssUpdateDate,
                'js/domLib.js'.$cssUpdateDate,
                'js/domTT.js'.$cssUpdateDate,
                'js/domTT_drag.js'.$cssUpdateDate,
                'js/fadomatic.js'.$cssUpdateDate,
            ],
            SafeHtml::fromTrustedHtml($addiCode),
            $picFolder,
        ];
    }

    /**
     * Header message alerts as plain records; the partial renders each
     * one as an `.nxm-alert` banner for both chrome variants. Mirrors the
     * promotion/warning/staff alert block that used to live in
     * PageLayout::renderHeader().
     *
     * @return list<array{url: string, text: SafeHtml, color: string}>
     */
    private static function loadAlerts(
        PageLayoutContext $context,
        int $userId,
        int $unread,
        PageLayoutRepositoryInterface $repo,
        bool $msgalert,
    ): array {
        if (! $msgalert) {
            return [];
        }

        $user = $context->user ?? [];
        $alerts = [];

        $timeline = TorrentState::resolveTimeline();
        $currentPromotion = $timeline['current'] ?? null;
        $upcomingPromotion = $timeline['upcoming'] ?? null;
        $remarkTpl = (string) (__('legacy/functions.full_site_promotion_remark'));
        if ($currentPromotion) {
            $promotionText = TorrentPromotion::fromIntSafe((int) ($currentPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
            $msg = sprintf((string) (__('legacy/functions.full_site_promotion_in_effect')), $promotionText);
            if (! empty($currentPromotion['begin']) || ! empty($currentPromotion['deadline'])) {
                $timeRange = sprintf((string) (__('legacy/functions.full_site_promotion_time_range')), $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
                $msg .= '<br/>'.$timeRange;
            }
            if (! empty($currentPromotion['remark'])) {
                $msg .= '<br/>'.sprintf($remarkTpl, $currentPromotion['remark']);
            }
            $alerts[] = ['url' => 'torrents.php', 'text' => $msg, 'color' => 'green'];
        }
        if ($upcomingPromotion) {
            $promotionText = TorrentPromotion::fromIntSafe((int) ($upcomingPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
            $msg = sprintf((string) (__('legacy/functions.full_site_promotion_upcoming')), $promotionText);
            if (! empty($upcomingPromotion['begin']) || ! empty($upcomingPromotion['deadline'])) {
                $timeRange = sprintf((string) (__('legacy/functions.full_site_promotion_time_range')), $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
                $msg .= '<br/>'.$timeRange;
            }
            if (! empty($upcomingPromotion['remark'])) {
                $msg .= '<br/>'.sprintf($remarkTpl, $upcomingPromotion['remark']);
            }
            $alerts[] = ['url' => 'torrents.php', 'text' => $msg, 'color' => 'blue'];
        }
        if ($user['leechwarn'] ?? false) {
            $kicktimeout = Time::format($user['leechwarnuntil'], false, false, true);
            $alerts[] = [
                'url' => 'faq.php#id17',
                'text' => (string) (__('legacy/functions.text_please_improve_ratio_within')).$kicktimeout.(string) (__('legacy/functions.text_or_you_will_be_banned')),
                'color' => 'orange',
            ];
        }
        if ($context->deleteNotTransferTwoAccount) {
            if (($user['downloaded'] ?? 0) == 0 && (($user['uploaded'] ?? 0) == 0 || ($user['uploaded'] ?? 0) == $context->iniUploadMain)) {
                $context->neverDeleteAccount = $context->neverDeleteAccount <= $context->vipClass ? $context->neverDeleteAccount : $context->vipClass;
                if ($context->userClass() < $context->neverDeleteAccount) {
                    $secs = $context->deleteNotTransferTwoAccount * 24 * 60 * 60;
                    $addedtime = strtotime((string) ($user['added'] ?? ''));
                    if ($addedtime + $secs / 3 < TIMENOW) {
                        $kicktimeout = Time::format(date('Y-m-d H:i:s', $addedtime + $secs), false, false, true);
                        $alerts[] = [
                            'url' => 'rules.php',
                            'text' => (string) (__('legacy/functions.text_please_download_something_within')).$kicktimeout.(string) (__('legacy/functions.text_inactive_account_be_deleted')),
                            'color' => 'gray',
                        ];
                    }
                }
            }
        }
        if ($user['showclienterror'] ?? false) {
            $alerts[] = ['url' => 'faq.php#id29', 'text' => (string) (__('legacy/functions.text_banned_client_warning')), 'color' => 'black'];
        }
        if ($unread) {
            $alerts[] = [
                'url' => 'messages.php',
                'text' => (string) (__('legacy/functions.text_you_have')).$unread.(string) (__('legacy/functions.text_new_message')).Strings::addS($unread).(string) (__('legacy/functions.text_click_here_to_read')),
                'color' => 'red',
            ];
        }

        foreach (MsgAlert::pendingAlerts() as $alert) {
            $alerts[] = $alert;
        }

        if (! preg_match('/index/i', $context->scriptFileName)) {
            $newNews = $context->cache?->get_value('user_'.$userId.'_unread_news_count');
            if ($newNews == '') {
                $newNews = $repo->getUnreadNewsCount($user['last_home'] ?? null);
                $context->cache?->cache_value('user_'.$userId.'_unread_news_count', $newNews, 300);
            }
            $newNews = (int) $newNews;
            if ($newNews > 0) {
                $alerts[] = [
                    'url' => 'index.php',
                    'text' => (string) (__('legacy/functions.text_there_is')).Strings::isOrAre($newNews).$newNews.(string) (__('legacy/functions.text_new_news')),
                    'color' => 'green',
                ];
            }
        }

        $staffMessages = app(StaffMessageRepository::class)->getStaffMessageCountCache($userId, 'new');
        if ($staffMessages === false) {
            $staffMessages = app(StaffMessageRepository::class)->countStaffMessage($userId, 0);
            app(StaffMessageRepository::class)->updateStaffMessageCountCache($userId, 'new', $staffMessages);
        }
        $staffMessages = (int) $staffMessages;
        if ($staffMessages > 0) {
            $alerts[] = [
                'url' => 'staffbox.php',
                'text' => (string) (__('legacy/functions.text_there_is')).Strings::isOrAre($staffMessages).$staffMessages.(string) (__('legacy/functions.text_new_staff_message')).Strings::addS($staffMessages),
                'color' => 'blue',
            ];
        }

        if (Permissions::userCan('torrent-approval', false, $userId) && Settings::get('torrent.approval_status_none_visible') == 'no') {
            $toApprovalCounts = $context->cache?->get_value('TORRENT_APPROVAL_NONE');
            if ($toApprovalCounts === false) {
                $toApprovalCounts = $repo->getTorrentApprovalNoneCount();
                $context->cache?->cache_value('TORRENT_APPROVAL_NONE', $toApprovalCounts, 60);
            }
            $toApprovalCounts = (int) $toApprovalCounts;
            if ($toApprovalCounts) {
                $alerts[] = [
                    'url' => 'torrents.php?approval_status=0&incldead=0',
                    'text' => sprintf((string) (__('legacy/functions.text_torrent_to_approval')), Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)),
                    'color' => 'darkred',
                ];
            }
        }

        if (Permissions::userCan('staffmem', false, $userId)) {
            $complaints = $context->cache?->get_value('COMPLAINTS_COUNT_CACHE');
            if ($complaints === false) {
                $complaints = $repo->getOpenComplaintsCount();
                $context->cache?->cache_value('COMPLAINTS_COUNT_CACHE', $complaints, 600);
            }
            $complaints = (int) $complaints;
            if ($complaints) {
                $alerts[] = [
                    'url' => 'complains.php?action=list',
                    'text' => sprintf((string) (__('legacy/functions.text_complains')), Strings::isOrAre($complaints), $complaints, Strings::addS($complaints)),
                    'color' => 'darkred',
                ];
            }
            $numReports = $context->cache?->get_value('staff_new_report_count');
            if ($numReports == '') {
                $numReports = $repo->getOpenReportsCount();
                $context->cache?->cache_value('staff_new_report_count', $numReports, 900);
            }
            $numReports = (int) $numReports;
            if ($numReports) {
                $alerts[] = [
                    'url' => 'reports.php',
                    'text' => (string) (__('legacy/functions.text_there_is')).Strings::isOrAre($numReports).$numReports.(string) (__('legacy/functions.text_new_report')).Strings::addS($numReports),
                    'color' => 'blue',
                ];
            }
            $numCheaters = $context->cache?->get_value('staff_new_cheater_count');
            if ($numCheaters == '') {
                $numCheaters = $repo->getOpenCheatersCount();
                $context->cache?->cache_value('staff_new_cheater_count', $numCheaters, 900);
            }
            $numCheaters = (int) $numCheaters;
            if ($numCheaters) {
                $alerts[] = [
                    'url' => 'cheaterbox.php',
                    'text' => (string) (__('legacy/functions.text_there_is')).Strings::isOrAre($numCheaters).$numCheaters.(string) (__('legacy/functions.text_new_suspected_cheater')).Strings::addS($numCheaters),
                    'color' => 'blue',
                ];
            }
        }

        $currentExam = (new Exam)->getCurrent($userId);
        if (! empty($currentExam['html']) && $currentExam['exam'] !== null) {
            $alerts[] = [
                'url' => $currentExam['exam']->type == ExamType::TASK->value ? 'task.php' : 'messages.php',
                'text' => $currentExam['html'],
                'color' => $currentExam['exam']->background_color ?? 'blue',
            ];
        }

        return array_map(
            static fn (array $alert): array => [
                'url' => (string) ($alert['url'] ?? ''),
                'text' => SafeHtml::fromTrustedHtml((string) ($alert['text'] ?? '')),
                'color' => self::alertColor((string) ($alert['color'] ?? '')),
            ],
            $alerts,
        );
    }

    /**
     * Banner colour whitelist — mirrors the legacy `msg-alert-*` classes;
     * unknown colours (e.g. a free-form exam `background_color`) fall back
     * to red just as `Html::messageAlert` did.
     */
    private static function alertColor(string $color): string
    {
        return in_array($color, ['red', 'green', 'black', 'blue', 'orange', 'gray'], true)
            ? $color
            : 'red';
    }

    /**
     * @return list<array{key: string, href: string, label: string, selected: bool, attrs: string}>
     */
    private static function navItems(PageLayoutContext $context): array
    {
        $script = $context->script !== '' ? $context->script : basename((string) $context->scriptFileName, '.php');
        $user = $context->user;
        $userId = (int) ($user['id'] ?? 0);

        $selected = match (1) {
            preg_match('/index/i', $script) => 'home',
            preg_match('/forums/i', $script) => 'forums',
            preg_match('/latestcomments/i', $script) => 'latestcomments',
            preg_match('/torrents/i', $script) => 'torrents',
            preg_match('/offers|offcomment/i', $script) => 'offers',
            preg_match('/upload/i', $script) => 'upload',
            preg_match('/usercp/i', $script) => 'usercp',
            preg_match('/topten/i', $script) => 'topten',
            preg_match('/log/i', $script) => 'log',
            preg_match('/rules/i', $script) => 'rules',
            preg_match('/faq/i', $script) => 'faq',
            preg_match('/contactstaff/i', $script) => 'contactstaff',
            preg_match('/staff/i', $script) => 'staff',
            default => '',
        };

        $normalSectionName = SearchBox::value($context->cache, (int) (Settings::get('main.browsecat') ?? 1), 'section_name');

        $items = [
            ['key' => 'home', 'href' => 'index.php', 'label' => __('legacy/functions.text_home')],
            ['key' => 'forums', 'href' => 'forums.php', 'label' => __('legacy/functions.text_forums')],
            ['key' => 'latestcomments', 'href' => 'latestcomments.php', 'label' => __('legacy/functions.text_latest_comments')],
            ['key' => 'torrents', 'href' => 'torrents.php', 'label' => $normalSectionName[$context->langDir] ?? (__('legacy/functions.text_torrents'))],
        ];
        if ($context->enableOffer === 'yes') {
            $items[] = ['key' => 'offers', 'href' => 'offers.php', 'label' => __('legacy/functions.text_offers')];
        }
        $items[] = ['key' => 'upload', 'href' => 'upload.php', 'label' => __('legacy/functions.text_upload')];
        if (Permissions::userCan('topten', false, $userId)) {
            $items[] = ['key' => 'topten', 'href' => 'topten.php', 'label' => __('legacy/functions.text_top_ten')];
        }
        if (Permissions::userCan('log', false, $userId)) {
            $items[] = ['key' => 'log', 'href' => 'log.php', 'label' => __('legacy/functions.text_log')];
        }
        $items[] = ['key' => 'rules', 'href' => 'rules.php', 'label' => __('legacy/functions.text_rules')];
        $items[] = ['key' => 'faq', 'href' => 'faq.php', 'label' => __('legacy/functions.text_faq')];
        if (Permissions::userCan('staffmem', false, $userId)) {
            $items[] = ['key' => 'staff', 'href' => 'staff.php', 'label' => __('legacy/functions.text_staff')];
        }
        $items[] = ['key' => 'contactstaff', 'href' => 'contactstaff.php', 'label' => __('legacy/functions.text_contactstaff')];

        return array_values(array_map(
            fn (array $item): array => $item + ['selected' => $item['key'] === $selected, 'attrs' => ''],
            $items,
        ));
    }

    /**
     * Footer script list per variant: the modern chrome defers all shared
     * libraries to the footer, while the legacy variant keeps its
     * historical head-loaded set (page bodies may reference them during
     * parse).
     *
     * @return list<string>
     */
    private static function footScripts(string $variant): array
    {
        $scripts = ['js/ajax.js', 'js/nexus.js', 'js/csrf.js'];
        if ($variant !== 'legacy') {
            $scripts = array_merge($scripts, [
                'js/common.js',
                'js/domLib.js',
                'js/domTT.js',
                'js/domTT_drag.js',
                'js/fadomatic.js',
            ]);
        }
        $scripts[] = 'js/medium-zoom.min.js';
        $scripts[] = 'js/goup.js';

        return $scripts;
    }

    private static function cachedCount(?LegacyRedisCache $cache, string $key, callable $producer, int $ttl): mixed
    {
        $value = $cache?->get_value($key);
        if ($value === false || $value === null || $value === '') {
            $value = $producer();
            $cache?->cache_value($key, $value, $ttl);
        }

        return $value;
    }
}
