<?php

namespace App\Support;

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
     * @return array{html: string, selected: string}
     */
    public static function render(
        string $scriptName,
        array $langFunctions,
        string $enableOffer,
        string $enableSpecial,
        ?string $customMenu,
    ): array {
        $selected = self::selectedItem($scriptName);

        if ($customMenu !== null && $customMenu !== '') {
            return [
                'html' => '<div id="nav">' . $customMenu . '</div>',
                'selected' => $selected,
            ];
        }

        $lang = get_langfolder_cookie();
        $normalSectionName = get_searchbox_value(get_setting('main.browsecat'), 'section_name');
        $specialSectionName = get_searchbox_value(get_setting('main.specialcat'), 'section_name');

        $items = [];
        $items[] = self::item($selected, 'home', 'index.php', $langFunctions['text_home'] ?? 'Home');
        $items[] = self::item($selected, 'forums', 'forums.php', $langFunctions['text_forums'] ?? 'Forums');
        $items[] = self::item($selected, 'torrents', 'torrents.php', $normalSectionName[$lang] ?? ($langFunctions['text_torrents'] ?? 'Torrents'), "rel='sub-menu'");

        if ($enableSpecial === 'yes' && user_can('view_special_torrent')) {
            $items[] = self::item($selected, 'special', 'special.php', $specialSectionName[$lang] ?? ($langFunctions['text_special'] ?? 'Special'));
        }
        if ($enableOffer === 'yes') {
            $items[] = self::item($selected, 'offers', 'offers.php', $langFunctions['text_offers'] ?? 'Offers');
        }
        $items[] = self::item($selected, 'upload', 'upload.php', $langFunctions['text_upload'] ?? 'Upload');
        if (user_can('topten')) {
            $items[] = self::item($selected, 'topten', 'topten.php', $langFunctions['text_top_ten'] ?? 'Top 10');
        }
        if (user_can('log')) {
            $items[] = self::item($selected, 'log', 'log.php', $langFunctions['text_log'] ?? 'Log');
        }
        $items[] = self::item($selected, 'rules', 'rules.php', $langFunctions['text_rules'] ?? 'Rules');
        $items[] = self::item($selected, 'faq', 'faq.php', $langFunctions['text_faq'] ?? 'FAQ');
        if (user_can('staffmem')) {
            $items[] = self::item($selected, 'staff', 'staff.php', $langFunctions['text_staff'] ?? 'Staff');
        }
        $items[] = self::item($selected, 'contactstaff', 'contactstaff.php', $langFunctions['text_contactstaff'] ?? 'Contact Staff');

        $html = '<div id="nav"><ul id="mainmenu" class="menu">' . implode('', $items) . '</ul></div>';

        return ['html' => $html, 'selected' => $selected];
    }

    private static function selectedItem(string $scriptName): string
    {
        return match (1) {
            preg_match('/index/i', $scriptName) => 'home',
            preg_match('/forums/i', $scriptName) => 'forums',
            preg_match('/torrents/i', $scriptName) => 'torrents',
            preg_match('/special/i', $scriptName) => 'special',
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

        return '<li' . $class . '><a href="' . $href . '"' . ($attrs ? ' ' . $attrs : '') . '>' . $label . '</a></li>';
    }
}
