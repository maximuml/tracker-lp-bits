<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Enums\ExamType;
use App\Enums\TorrentPromotion;
use App\Models\TorrentState;
use App\Support\Exam;
use App\Support\Html\SafeHtml;
use App\Support\PageLayoutContext;
use App\Support\Settings;
use App\Support\Strings;
use App\Support\Time;
use App\Utils\MsgAlert;

/**
 * Header message alerts for the shared chrome (ADR 0018): promotion,
 * ratio/inactivity warnings, unread news/staff messages, staff queue
 * counters and the current exam banner — collected as plain records; the
 * partial renders each one as an `.nxm-alert` banner for both chrome
 * variants. Mirrors the promotion/warning/staff alert block that used to
 * live in PageRenderer::renderHeader().
 */
final class ChromeAlerts
{
    private function __construct() {}

    /**
     * @return list<array{url: string, text: SafeHtml, color: string}>
     */
    public static function load(
        PageLayoutContext $context,
        int $userId,
        int $unread,
        PageLayoutRepositoryInterface $repo,
        bool $msgalert,
        ChromeRepositories $chrome,
    ): array {
        if (! $msgalert) {
            return [];
        }

        $user = $context->user ?? [];
        $alerts = [];

        $timeline = TorrentState::resolveTimeline();
        $currentPromotion = $timeline['current'] ?? null;
        $upcomingPromotion = $timeline['upcoming'] ?? null;
        $remarkTpl = (string) (__('functions.full_site_promotion_remark'));
        if ($currentPromotion) {
            $promotionText = TorrentPromotion::fromIntSafe((int) ($currentPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
            $lines = [sprintf((string) (__('functions.full_site_promotion_in_effect')), $promotionText)];
            if (! empty($currentPromotion['begin']) || ! empty($currentPromotion['deadline'])) {
                $lines[] = sprintf((string) (__('functions.full_site_promotion_time_range')), $currentPromotion['begin'] ?? '-∞', $currentPromotion['deadline'] ?? '∞');
            }
            if (! empty($currentPromotion['remark'])) {
                $lines[] = sprintf($remarkTpl, $currentPromotion['remark']);
            }
            $alerts[] = ['url' => '/web/torrents', 'text' => implode(' · ', $lines), 'color' => 'green'];
        }
        if ($upcomingPromotion) {
            $promotionText = TorrentPromotion::fromIntSafe((int) ($upcomingPromotion['global_sp_state'] ?? TorrentPromotion::NORMAL->value))->label();
            $lines = [sprintf((string) (__('functions.full_site_promotion_upcoming')), $promotionText)];
            if (! empty($upcomingPromotion['begin']) || ! empty($upcomingPromotion['deadline'])) {
                $lines[] = sprintf((string) (__('functions.full_site_promotion_time_range')), $upcomingPromotion['begin'] ?? '-∞', $upcomingPromotion['deadline'] ?? '∞');
            }
            if (! empty($upcomingPromotion['remark'])) {
                $lines[] = sprintf($remarkTpl, $upcomingPromotion['remark']);
            }
            $alerts[] = ['url' => '/web/torrents', 'text' => implode(' · ', $lines), 'color' => 'blue'];
        }
        if ($user['leechwarn'] ?? false) {
            $kicktimeout = Time::format($user['leechwarnuntil'], false, false, true);
            $alerts[] = [
                'url' => '/web/faq#id17',
                'text' => (string) (__('functions.text_please_improve_ratio_within')).$kicktimeout.(string) (__('functions.text_or_you_will_be_banned')),
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
                            'url' => '/web/rules',
                            'text' => (string) (__('functions.text_please_download_something_within')).$kicktimeout.(string) (__('functions.text_inactive_account_be_deleted')),
                            'color' => 'gray',
                        ];
                    }
                }
            }
        }
        if ($user['showclienterror'] ?? false) {
            $alerts[] = ['url' => '/web/faq#id29', 'text' => (string) (__('functions.text_banned_client_warning')), 'color' => 'black'];
        }
        foreach (MsgAlert::pendingAlerts() as $alert) {
            $alerts[] = $alert;
        }

        if (! preg_match('/index/i', $context->scriptFileName)) {
            $newNews = $context->cache?->get('user_'.$userId.'_unread_news_count');
            if ($newNews == '') {
                $newNews = $repo->getUnreadNewsCount($user['last_home'] ?? null);
                $context->cache?->put('user_'.$userId.'_unread_news_count', $newNews, 300);
            }
            $newNews = (int) $newNews;
            if ($newNews > 0) {
                $alerts[] = [
                    'url' => '/web/index',
                    'text' => (string) (__('functions.text_there_is')).Strings::isOrAre($newNews).$newNews.(string) (__('functions.text_new_news')),
                    'color' => 'green',
                ];
            }
        }

        $staffMessages = $chrome->staffMessages->getStaffMessageCountCache($userId, 'new');
        if ($staffMessages === false) {
            $staffMessages = $chrome->staffMessages->countStaffMessage($userId, 0);
            $chrome->staffMessages->updateStaffMessageCountCache($userId, 'new', $staffMessages);
        }
        $staffMessages = (int) $staffMessages;
        if ($staffMessages > 0) {
            $alerts[] = [
                'url' => '/staffbox',
                'text' => (string) (__('functions.text_there_is')).Strings::isOrAre($staffMessages).$staffMessages.(string) (__('functions.text_new_staff_message')).Strings::addS($staffMessages),
                'color' => 'blue',
            ];
        }

        if ($chrome->permissionChecker->userCan('torrent-approval', false, $userId) && Settings::get('torrent.approval_status_none_visible') == 'no') {
            $toApprovalCounts = $context->cache?->get('TORRENT_APPROVAL_NONE');
            if ($toApprovalCounts === false) {
                $toApprovalCounts = $repo->getTorrentApprovalNoneCount();
                $context->cache?->put('TORRENT_APPROVAL_NONE', $toApprovalCounts, 60);
            }
            $toApprovalCounts = (int) $toApprovalCounts;
            if ($toApprovalCounts) {
                $alerts[] = [
                    'url' => '/web/torrents?approval_status=0&incldead=0',
                    'text' => sprintf((string) (__('functions.text_torrent_to_approval')), Strings::isOrAre($toApprovalCounts), $toApprovalCounts, Strings::addS($toApprovalCounts)),
                    'color' => 'darkred',
                ];
            }
        }

        if ($chrome->permissionChecker->userCan('staffmem', false, $userId)) {
            $complaints = $context->cache?->get('COMPLAINTS_COUNT_CACHE');
            if ($complaints === false) {
                $complaints = $repo->getOpenComplaintsCount();
                $context->cache?->put('COMPLAINTS_COUNT_CACHE', $complaints, 600);
            }
            $complaints = (int) $complaints;
            if ($complaints) {
                $alerts[] = [
                    'url' => '/web/complains?action=list',
                    'text' => sprintf((string) (__('functions.text_complains')), Strings::isOrAre($complaints), $complaints, Strings::addS($complaints)),
                    'color' => 'darkred',
                ];
            }
            $numReports = $context->cache?->get('staff_new_report_count');
            if ($numReports == '') {
                $numReports = $repo->getOpenReportsCount();
                $context->cache?->put('staff_new_report_count', $numReports, 900);
            }
            $numReports = (int) $numReports;
            if ($numReports) {
                $alerts[] = [
                    'url' => '/web/reports',
                    'text' => (string) (__('functions.text_there_is')).Strings::isOrAre($numReports).$numReports.(string) (__('functions.text_new_report')).Strings::addS($numReports),
                    'color' => 'blue',
                ];
            }
            $numCheaters = $context->cache?->get('staff_new_cheater_count');
            if ($numCheaters == '') {
                $numCheaters = $repo->getOpenCheatersCount();
                $context->cache?->put('staff_new_cheater_count', $numCheaters, 900);
            }
            $numCheaters = (int) $numCheaters;
            if ($numCheaters) {
                $alerts[] = [
                    'url' => '/cheaterbox',
                    'text' => (string) (__('functions.text_there_is')).Strings::isOrAre($numCheaters).$numCheaters.(string) (__('functions.text_new_suspected_cheater')).Strings::addS($numCheaters),
                    'color' => 'blue',
                ];
            }
        }

        $currentExam = (new Exam)->getCurrent($userId);
        if (! empty($currentExam['html']) && $currentExam['exam'] !== null) {
            $alerts[] = [
                'url' => $currentExam['exam']->type == ExamType::TASK->value ? '/web/task' : '/web/messages',
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
}
