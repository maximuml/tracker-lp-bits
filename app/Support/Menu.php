<?php

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;
use Nexus\Nexus;

/**
 * Legacy main-menu helper extracted from `include/functions.php`.
 *
 * Backs `menu()`. It builds the main navigation HTML and returns both
 * the rendered markup and the selected section key so the wrapper can
 * keep the `$USERUPDATESET` page-tracking side effect out of the
 * support class.
 */
final class Menu
{
    /**
     * Build the main menu.
     *
     * @param  array<string, string>  $langFunctions
     * @param  array<string, mixed>|null  $user
     * @return array{html: string, selected: string}
     */
    public static function render(
        string $scriptName,
        array $langFunctions,
        string $enableOffer,
        ?string $customMenu,
        ?array $user = null,
        ?LegacyRedisCache $cache = null,
        string $langDir = '',
    ): array {
        $selected = self::selectedItem($scriptName);

        if ($customMenu !== null && $customMenu !== '') {
            return [
                'html' => '<div id="nav">'.$customMenu.'</div>',
                'selected' => $selected,
            ];
        }

        $userId = (int) ($user['id'] ?? 0);
        $normalSectionName = SearchBox::value($cache, (int) (Settings::get('main.browsecat') ?? 1), 'section_name');

        $items = [];
        $items[] = self::item($selected, 'home', 'index.php', $langFunctions['text_home'] ?? 'Home');
        $items[] = self::item($selected, 'forums', 'forums.php', $langFunctions['text_forums'] ?? 'Forums');
        $items[] = self::item($selected, 'latestcomments', 'latestcomments.php', $langFunctions['text_latest_comments'] ?? 'Latest Comments');
        $items[] = self::item($selected, 'torrents', 'torrents.php', $normalSectionName[$langDir] ?? ($langFunctions['text_torrents'] ?? 'Torrents'), "rel='sub-menu'");

        if ($enableOffer === 'yes') {
            $items[] = self::item($selected, 'offers', 'offers.php', $langFunctions['text_offers'] ?? 'Offers');
        }
        $items[] = self::item($selected, 'upload', 'upload.php', $langFunctions['text_upload'] ?? 'Upload');
        if (Permissions::userCan('topten', false, $userId)) {
            $items[] = self::item($selected, 'topten', 'topten.php', $langFunctions['text_top_ten'] ?? 'Top 10');
        }
        if (Permissions::userCan('log', false, $userId)) {
            $items[] = self::item($selected, 'log', 'log.php', $langFunctions['text_log'] ?? 'Log');
        }
        $items[] = self::item($selected, 'rules', 'rules.php', $langFunctions['text_rules'] ?? 'Rules');
        $items[] = self::item($selected, 'faq', 'faq.php', $langFunctions['text_faq'] ?? 'FAQ');
        if (Permissions::userCan('staffmem', false, $userId)) {
            $items[] = self::item($selected, 'staff', 'staff.php', $langFunctions['text_staff'] ?? 'Staff');
        }
        $items[] = self::item($selected, 'contactstaff', 'contactstaff.php', $langFunctions['text_contactstaff'] ?? 'Contact Staff');

        $html = '<div id="nav"><ul id="mainmenu" class="menu">'.implode('', $items).'</ul></div>';

        return ['html' => $html, 'selected' => $selected];
    }

    /**
     * Render and emit the main menu using values from the current request context.
     *
     * Backs the legacy `menu()` helper.
     */
    public static function outputWithContext(string $selected = 'home'): void
    {
        $customMenu = (string) Hooks::applyFilter('nexus_menu');

        $result = self::render(
            \function_exists('nexus') ? Nexus::instance()->getScript() : '',
            SupportContext::getLangFunctions(),
            (string) SupportContext::getGlobal('enableoffer', ''),
            $customMenu !== '' ? $customMenu : null,
            SupportContext::getUser(),
            SupportContext::getCache(),
            (string) SupportContext::getGlobal('CURLANGDIR', ''),
        );

        $user = SupportContext::getUser();
        if ($user && SupportContext::getGlobal('where_tweak', '') === 'yes') {
            SupportContext::addUserUpdate('page', $result['selected']);
        }

        echo $result['html'];
    }

    private static function selectedItem(string $scriptName): string
    {
        return match (1) {
            preg_match('/index/i', $scriptName) => 'home',
            preg_match('/forums/i', $scriptName) => 'forums',
            preg_match('/latestcomments/i', $scriptName) => 'latestcomments',
            preg_match('/torrents/i', $scriptName) => 'torrents',
            preg_match('/offers/i', $scriptName), preg_match('/offcomment/i', $scriptName) => 'offers',
            preg_match('/upload/i', $scriptName) => 'upload',
            preg_match('/usercp/i', $scriptName) => 'usercp',
            preg_match('/topten/i', $scriptName) => 'topten',
            preg_match('/log/i', $scriptName) => 'log',
            preg_match('/rules/i', $scriptName) => 'rules',
            preg_match('/faq/i', $scriptName) => 'faq',
            preg_match('/contactstaff/i', $scriptName) => 'contactstaff',
            preg_match('/staff/i', $scriptName) => 'staff',
            default => '',
        };
    }

    private static function item(string $selected, string $key, string $href, string $label, string $attrs = ''): string
    {
        $class = $selected === $key ? ' class="selected"' : '';

        return '<li'.$class.'><a href="'.$href.'"'.($attrs ? ' '.$attrs : '').'>'.$label.'</a></li>';
    }
}
