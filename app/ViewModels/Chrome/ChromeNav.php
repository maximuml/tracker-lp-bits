<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Support\PageLayoutContext;
use App\Support\SearchBox;
use App\Support\Settings;

/**
 * Main navigation items for the shared chrome (ADR 0018): static list
 * gated by permissions/settings with the current section marked selected.
 */
final class ChromeNav
{
    private function __construct() {}

    /**
     * @return list<array{key: string, href: string, label: string, selected: bool, attrs: string}>
     */
    public static function items(PageLayoutContext $context, ChromeRepositories $chrome): array
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
            ['key' => 'home', 'href' => '/web/index', 'label' => __('legacy/functions.text_home')],
            ['key' => 'forums', 'href' => '/forums', 'label' => __('legacy/functions.text_forums')],
            ['key' => 'latestcomments', 'href' => '/web/latestcomments', 'label' => __('legacy/functions.text_latest_comments')],
            ['key' => 'torrents', 'href' => '/web/torrents', 'label' => $normalSectionName[$context->langDir] ?? (__('legacy/functions.text_torrents'))],
        ];
        if ($context->enableOffer === 'yes') {
            $items[] = ['key' => 'offers', 'href' => '/web/offers', 'label' => __('legacy/functions.text_offers')];
        }
        $items[] = ['key' => 'upload', 'href' => '/web/upload', 'label' => __('legacy/functions.text_upload')];
        if ($chrome->permissionChecker->userCan('topten', false, $userId)) {
            $items[] = ['key' => 'topten', 'href' => '/web/topten', 'label' => __('legacy/functions.text_top_ten')];
        }
        if ($chrome->permissionChecker->userCan('log', false, $userId)) {
            $items[] = ['key' => 'log', 'href' => '/web/log', 'label' => __('legacy/functions.text_log')];
        }
        $items[] = ['key' => 'rules', 'href' => '/web/rules', 'label' => __('legacy/functions.text_rules')];
        $items[] = ['key' => 'faq', 'href' => '/web/faq', 'label' => __('legacy/functions.text_faq')];
        if ($chrome->permissionChecker->userCan('staffmem', false, $userId)) {
            $items[] = ['key' => 'staff', 'href' => '/staff', 'label' => __('legacy/functions.text_staff')];
        }
        $items[] = ['key' => 'contactstaff', 'href' => '/contactstaff', 'label' => __('legacy/functions.text_contactstaff')];

        return array_values(array_map(
            fn (array $item): array => $item + ['selected' => $item['key'] === $selected, 'attrs' => ''],
            $items,
        ));
    }
}
