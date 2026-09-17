<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;

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
     * @param  array<string, mixed>|null  $user
     * @return array{html: string, selected: string}
     */
    public function render(
        string $scriptName,
        string $enableOffer,
        ?string $customMenu,
        ?array $user = null,
        ?LegacyRedisCache $cache = null,
        string $langDir = '',
    ): array {
        $selected = $this->selectedItem($scriptName);

        if ($customMenu !== null && $customMenu !== '') {
            return [
                'html' => '<div id="nav">'.$customMenu.'</div>',
                'selected' => $selected,
            ];
        }

        $userId = (int) ($user['id'] ?? 0);
        $normalSectionName = SearchBox::value($cache, (int) (Settings::get('main.browsecat') ?? 1), 'section_name');

        $items = [];
        $items[] = $this->item($selected, 'home', 'index.php', __('legacy/functions.text_home'));
        $items[] = $this->item($selected, 'forums', 'forums.php', __('legacy/functions.text_forums'));
        $items[] = $this->item($selected, 'latestcomments', 'latestcomments.php', __('legacy/functions.text_latest_comments'));
        $items[] = $this->item($selected, 'torrents', 'torrents.php', $normalSectionName[$langDir] ?? (__('legacy/functions.text_torrents')), "rel='sub-menu'");

        if ($enableOffer === 'yes') {
            $items[] = $this->item($selected, 'offers', 'offers.php', __('legacy/functions.text_offers'));
        }
        $items[] = $this->item($selected, 'upload', 'upload.php', __('legacy/functions.text_upload'));
        if (Permissions::userCan('topten', false, $userId)) {
            $items[] = $this->item($selected, 'topten', 'topten.php', __('legacy/functions.text_top_ten'));
        }
        if (Permissions::userCan('log', false, $userId)) {
            $items[] = $this->item($selected, 'log', 'log.php', __('legacy/functions.text_log'));
        }
        $items[] = $this->item($selected, 'rules', 'rules.php', __('legacy/functions.text_rules'));
        $items[] = $this->item($selected, 'faq', 'faq.php', __('legacy/functions.text_faq'));
        if (Permissions::userCan('staffmem', false, $userId)) {
            $items[] = $this->item($selected, 'staff', 'staff.php', __('legacy/functions.text_staff'));
        }
        $items[] = $this->item($selected, 'contactstaff', 'contactstaff.php', __('legacy/functions.text_contactstaff'));

        $html = '<div id="nav"><ul id="mainmenu" class="menu">'.implode('', $items).'</ul></div>';

        return ['html' => $html, 'selected' => $selected];
    }

    private function selectedItem(string $scriptName): string
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

    private function item(string $selected, string $key, string $href, string $label, string $attrs = ''): string
    {
        $class = $selected === $key ? ' class="selected"' : '';

        return '<li'.$class.'><a href="'.$href.'"'.($attrs ? ' '.$attrs : '').'>'.$label.'</a></li>';
    }
}
