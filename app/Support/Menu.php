<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\PermissionChecker;
use App\Support\Cache\NexusCache;
use App\Support\Html\SafeHtml;

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
        ?NexusCache $cache = null,
        string $langDir = '',
    ): array {
        $selected = $this->selectedItem($scriptName);

        if ($customMenu !== null && $customMenu !== '') {
            return [
                'html' => view('support._menu-custom', ['customMenu' => SafeHtml::fromTrustedHtml($customMenu)])->render(),
                'selected' => $selected,
            ];
        }

        $userId = (int) ($user['id'] ?? 0);
        $normalSectionName = SearchBox::value($cache, (int) (Settings::get('main.browsecat') ?? 1), 'section_name');

        $items = [];
        $items[] = $this->item($selected, 'home', 'index.php', __('functions.text_home'));
        $items[] = $this->item($selected, 'forums', '/forums', __('functions.text_forums'));
        $items[] = $this->item($selected, 'latestcomments', '/web/latestcomments', __('functions.text_latest_comments'));
        $items[] = $this->item($selected, 'torrents', '/web/torrents', $normalSectionName[$langDir] ?? (__('functions.text_torrents')), true);

        if ($enableOffer === 'yes') {
            $items[] = $this->item($selected, 'offers', '/web/offers', __('functions.text_offers'));
        }
        $items[] = $this->item($selected, 'upload', '/web/upload', __('functions.text_upload'));
        if (PermissionChecker::instance()->userCan('topten', false, $userId)) {
            $items[] = $this->item($selected, 'topten', '/web/topten', __('functions.text_top_ten'));
        }
        if (PermissionChecker::instance()->userCan('log', false, $userId)) {
            $items[] = $this->item($selected, 'log', '/web/log', __('functions.text_log'));
        }
        $items[] = $this->item($selected, 'rules', '/web/rules', __('functions.text_rules'));
        $items[] = $this->item($selected, 'faq', '/web/faq', __('functions.text_faq'));
        if (PermissionChecker::instance()->userCan('staffmem', false, $userId)) {
            $items[] = $this->item($selected, 'staff', '/web/staff', __('functions.text_staff'));
        }
        $items[] = $this->item($selected, 'contactstaff', '/web/contactstaff', __('functions.text_contactstaff'));

        $html = view('support._menu', ['items' => $items])->render();

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

    private function item(string $selected, string $key, string $href, string $label, bool $subMenu = false): SafeHtml
    {
        return SafeHtml::fromTrustedHtml(trim(view('support._menu-item', [
            'href' => $href,
            'label' => $label,
            'selected' => $selected === $key,
            'subMenu' => $subMenu,
        ])->render()));
    }
}
