<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\PageLayoutContext;
use App\Support\Permissions;
use App\Support\Ratio;
use App\Support\SearchBox;
use App\Support\Settings;
use App\Support\UserDisplay;

/**
 * Data for the modern page chrome (`layouts/modern`).
 *
 * Variant A (ADR 0014): key pages render a semantic HTML5 shell instead of
 * `PageLayout::headerHtml()` + `Frame::`. This view model gathers the same
 * user-bar fields the legacy header computed, but returns plain data —
 * markup lives in the layout.
 */
final class SiteChromeViewModel
{
    /**
     * @param  array<string, string>  $lang
     * @param  array<string, mixed>|null  $user
     * @param  list<array{key: string, href: string, label: string, selected: bool, attrs: string}>  $navItems
     */
    private function __construct(
        public readonly string $siteName,
        public readonly string $slogan,
        public readonly string $logoMain,
        public readonly string $baseUrl,
        public readonly string $title,
        public readonly array $lang,
        public readonly ?array $user,
        public readonly array $navItems,
        public readonly SafeHtml $usernameHtml,
        public readonly string $ratio,
        public readonly string $uploaded,
        public readonly string $downloaded,
        public readonly string $seedbonus,
        public readonly int $inboxCount,
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
    ) {}

    public static function load(string $title, PageLayoutRepositoryInterface $repo): self
    {
        $context = PageLayoutContext::fromSupportContext();
        $lang = $context->lang;
        $user = $context->user;
        $cache = $context->cache;

        $navItems = self::navItems($context);
        $usernameHtml = SafeHtml::fromTrustedHtml('');
        $ratio = '';
        $uploaded = '';
        $downloaded = '';
        $seedbonus = '';
        $inboxCount = 0;
        $unreadCount = 0;
        $activeSeed = 0;
        $activeLeech = 0;
        $invites = '';
        $pendingInvites = 0;
        $isModerator = false;
        $isSysop = false;

        if ($user !== null && ! empty($user['id'])) {
            $userId = (int) $user['id'];
            $usernameHtml = SafeHtml::fromTrustedHtml(UserDisplay::username($userId));
            $ratio = (string) Ratio::forUserId($userId);
            $uploaded = Format::size((int) ($user['uploaded'] ?? 0));
            $downloaded = Format::size((int) ($user['downloaded'] ?? 0));
            $seedbonus = number_format((float) ($user['seedbonus'] ?? 0), 1);
            $invites = (string) ($user['invites'] ?? '0');
            $isModerator = $context->userClass() >= $context->moderatorClass;
            $isSysop = $context->userClass() >= $context->sysopClass;

            $inboxCount = (int) self::cachedCount($cache, 'user_'.$userId.'_inbox_count', fn () => $repo->getInboxCount($userId), 900);
            $unreadCount = (int) self::cachedCount($cache, 'user_'.$userId.'_unread_message_count', fn () => $repo->getUnreadMessageCount($userId), 60);
            $activeSeed = (int) self::cachedCount($cache, 'user_'.$userId.'_active_seed_count', fn () => $repo->getActiveSeedCount($userId), 60);
            $activeLeech = (int) self::cachedCount($cache, 'user_'.$userId.'_active_leech_count', fn () => $repo->getActiveLeechCount($userId), 60);
            $pendingInvites = (int) $repo->getPendingInviteCount($userId);
        }

        $fullTitle = $title === '' ? $context->siteName : $context->siteName.' :: '.$title;

        return new self(
            siteName: $context->siteName,
            slogan: $context->slogan,
            logoMain: $context->logoMain,
            baseUrl: $context->baseUrl,
            title: $fullTitle.' - Powered by '.PROJECTNAME,
            lang: $lang,
            user: $user,
            navItems: $navItems,
            usernameHtml: $usernameHtml,
            ratio: $ratio,
            uploaded: $uploaded,
            downloaded: $downloaded,
            seedbonus: $seedbonus,
            inboxCount: $inboxCount,
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
        );
    }

    /**
     * @return list<array{key: string, href: string, label: string, selected: bool, attrs: string}>
     */
    private static function navItems(PageLayoutContext $context): array
    {
        $script = $context->script !== '' ? $context->script : basename((string) $context->scriptFileName, '.php');
        $lang = $context->lang;
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
            ['key' => 'home', 'href' => 'index.php', 'label' => $lang['text_home'] ?? 'Home'],
            ['key' => 'forums', 'href' => 'forums.php', 'label' => $lang['text_forums'] ?? 'Forums'],
            ['key' => 'latestcomments', 'href' => 'latestcomments.php', 'label' => $lang['text_latest_comments'] ?? 'Latest Comments'],
            ['key' => 'torrents', 'href' => 'torrents.php', 'label' => $normalSectionName[$context->langDir] ?? ($lang['text_torrents'] ?? 'Torrents')],
        ];
        if ($context->enableOffer === 'yes') {
            $items[] = ['key' => 'offers', 'href' => 'offers.php', 'label' => $lang['text_offers'] ?? 'Offers'];
        }
        $items[] = ['key' => 'upload', 'href' => 'upload.php', 'label' => $lang['text_upload'] ?? 'Upload'];
        if (Permissions::userCan('topten', false, $userId)) {
            $items[] = ['key' => 'topten', 'href' => 'topten.php', 'label' => $lang['text_top_ten'] ?? 'Top 10'];
        }
        if (Permissions::userCan('log', false, $userId)) {
            $items[] = ['key' => 'log', 'href' => 'log.php', 'label' => $lang['text_log'] ?? 'Log'];
        }
        $items[] = ['key' => 'rules', 'href' => 'rules.php', 'label' => $lang['text_rules'] ?? 'Rules'];
        $items[] = ['key' => 'faq', 'href' => 'faq.php', 'label' => $lang['text_faq'] ?? 'FAQ'];
        if (Permissions::userCan('staffmem', false, $userId)) {
            $items[] = ['key' => 'staff', 'href' => 'staff.php', 'label' => $lang['text_staff'] ?? 'Staff'];
        }
        $items[] = ['key' => 'contactstaff', 'href' => 'contactstaff.php', 'label' => $lang['text_contactstaff'] ?? 'Contact Staff'];

        return array_values(array_map(
            fn (array $item): array => $item + ['selected' => $item['key'] === $selected, 'attrs' => ''],
            $items,
        ));
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
