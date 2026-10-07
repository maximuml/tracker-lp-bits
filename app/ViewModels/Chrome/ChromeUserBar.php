<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Models\HitAndRun;
use App\Models\User;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Env;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\PageLayoutContext;
use App\Support\Ratio;
use App\Support\Slots;
use App\Support\UserDisplay;

/**
 * Per-user chrome row data: greeting links, message/transfer stats and
 * staff icon counters (ADR 0018). Everything inside the
 * `@if($chrome->user)` header block comes from this view model; guests and
 * `skipUserData` callers get the all-default instance.
 */
final class ChromeUserBar
{
    private function __construct(
        public readonly SafeHtml $usernameHtml,
        public readonly SafeHtml $ratio,
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
        public readonly bool $attendanceDone,
        public readonly int $attendancePoints,
        public readonly int $attendanceCard,
        public readonly string $taskLabel,
        public readonly string $managementHref,
        public readonly ?bool $connectable,
        public readonly int $maxSlots,
        public readonly bool $hitAndRunEnabled,
        public readonly SafeHtml $hitAndRunStatsHtml,
        public readonly bool $canStaffmem,
        public readonly int $cheaterCount,
        public readonly int $reportCount,
        public readonly int $staffMessageTotal,
    ) {}

    public static function load(
        PageLayoutContext $context,
        PageLayoutRepositoryInterface $repo,
        ChromeRepositories $chrome,
        bool $skipUserData = false,
    ): self {
        $user = $context->user;
        if ($user === null || empty($user['id']) || $skipUserData) {
            return self::empty();
        }

        $userId = (int) $user['id'];
        $cache = $context->cache;

        $peerCounts = null;
        $resolvePeerCounts = static function () use (&$peerCounts, $repo, $userId): array {
            return $peerCounts ??= $repo->getActivePeerCounts($userId);
        };

        $connect = self::cachedCount($cache, 'user_'.$userId.'_connect', fn () => $repo->getConnectable($userId), 900);
        $connectable = $connect === 1 ? true : ($connect === 0 ? false : null);

        $slotsEnabled = $context->maxdlSystem === 'yes' && $context->userClass() < $context->vipClass;
        $maxSlots = $slotsEnabled ? Slots::maxDownloadSlots((int) $user['uploaded'], (int) $user['downloaded']) : 0;

        $attendance = $chrome->attendance->getAttendance($userId, date('Ymd'));

        $managementHref = '';
        if ($context->userClass() >= User::getAccessAdminClassMin()) {
            $managementHref = '/'.ltrim((string) Env::get('FILAMENT_PATH', 'nexusphp'), '/');
        }

        $hitAndRunEnabled = HitAndRun::getIsEnabled();
        $hitAndRunStatsHtml = SafeHtml::fromTrustedHtml('');
        if ($hitAndRunEnabled) {
            $hitAndRunStatsHtml = SafeHtml::fromTrustedHtml(
                (string) $chrome->hitAndRun->getStatusStats($userId)
            );
        }

        $canStaffmem = $chrome->permissionChecker->userCan('staffmem', false, $userId);
        $cheaterCount = 0;
        $reportCount = 0;
        if ($canStaffmem) {
            $cheaterCount = (int) self::cachedCount($cache, 'staff_cheater_count', fn () => $repo->getTotalCheaters(), 900);
            $reportCount = (int) self::cachedCount($cache, 'staff_report_count', fn () => $repo->getTotalReports(), 900);
        }

        $staffMessageTotal = $chrome->staffMessages->getStaffMessageCountCache($userId, 'total');
        if ($staffMessageTotal === false) {
            $staffMessageTotal = $chrome->staffMessages->countStaffMessage($userId);
            $chrome->staffMessages->updateStaffMessageCountCache($userId, 'total', $staffMessageTotal);
        }

        self::appendToastAssets($userId);

        return new self(
            usernameHtml: UserDisplay::username($userId),
            ratio: SafeHtml::fromTrustedHtml((string) Ratio::forUserId($userId)),
            uploaded: Format::size((int) ($user['uploaded'] ?? 0)),
            downloaded: Format::size((int) ($user['downloaded'] ?? 0)),
            seedbonus: number_format((float) ($user['seedbonus'] ?? 0), 1),
            inboxCount: (int) self::cachedCount($cache, 'user_'.$userId.'_inbox_count', fn () => $repo->getInboxCount($userId), 900),
            outboxCount: (int) self::cachedCount($cache, 'user_'.$userId.'_outbox_count', fn () => $repo->getOutboxCount($userId), 900),
            unreadCount: (int) self::cachedCount($cache, 'user_'.$userId.'_unread_message_count', fn () => $repo->getUnreadMessageCount($userId), 60),
            activeSeed: (int) self::cachedCount($cache, 'user_'.$userId.'_active_seed_count', fn () => $resolvePeerCounts()['seed'], 60),
            activeLeech: (int) self::cachedCount($cache, 'user_'.$userId.'_active_leech_count', fn () => $resolvePeerCounts()['leech'], 60),
            invites: (string) ($user['invites'] ?? '0'),
            pendingInvites: (int) $repo->getPendingInviteCount($userId),
            isModerator: $context->userClass() >= $context->moderatorClass,
            isSysop: $context->userClass() >= $context->sysopClass,
            attendanceDone: $attendance !== null,
            attendancePoints: $attendance ? (int) $attendance->points : 0,
            attendanceCard: $attendance ? (int) ($user['attendance_card'] ?? 0) : 0,
            taskLabel: Locale::trans('exam.type_task'),
            managementHref: $managementHref,
            connectable: $connectable,
            maxSlots: $maxSlots,
            hitAndRunEnabled: $hitAndRunEnabled,
            hitAndRunStatsHtml: $hitAndRunStatsHtml,
            canStaffmem: $canStaffmem,
            cheaterCount: $cheaterCount,
            reportCount: $reportCount,
            staffMessageTotal: (int) $staffMessageTotal,
        );
    }

    private static function empty(): self
    {
        return new self(
            usernameHtml: SafeHtml::fromTrustedHtml(''),
            ratio: SafeHtml::fromTrustedHtml(''),
            uploaded: '',
            downloaded: '',
            seedbonus: '',
            inboxCount: 0,
            outboxCount: 0,
            unreadCount: 0,
            activeSeed: 0,
            activeLeech: 0,
            invites: '',
            pendingInvites: 0,
            isModerator: false,
            isSysop: false,
            attendanceDone: false,
            attendancePoints: 0,
            attendanceCard: 0,
            taskLabel: '',
            managementHref: '',
            connectable: null,
            maxSlots: 0,
            hitAndRunEnabled: false,
            hitAndRunStatsHtml: SafeHtml::fromTrustedHtml(''),
            canStaffmem: false,
            cheaterCount: 0,
            reportCount: 0,
            staffMessageTotal: 0,
        );
    }

    private static function appendToastAssets(int $userId): void
    {
        $toastLang = json_encode([
            'newMessage' => __('index.toast_new_message'),
            'shoutboxMention' => __('index.toast_shoutbox_mention'),
            'from' => __('index.toast_from'),
            'close' => __('index.toast_close'),
            'bell' => __('notifications.title_bell'),
            'markAllRead' => __('notifications.mark_all_read'),
            'showMore' => __('notifications.show_more'),
            'empty' => __('notifications.empty'),
            'loadError' => __('notifications.load_error'),
            'userId' => $userId,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        AssetAppender::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
        AssetAppender::css('styles/toast.css', 'header', true);
        AssetAppender::js('js/toast.js', 'footer', true);
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
