<?php

declare(strict_types=1);

namespace App\ViewModels\Bonus;

use App\Support\Html\SafeHtml;

/**
 * The bonus exchange (shop) table of the mybonus page — replaces the
 * ob_start/echo block in `BonusPageService::buildShopTable()`.
 *
 * `msg` is trusted markup: the `vip` success message embeds
 * `UserClass::name()` output which is already HTML.
 */
final readonly class BonusShopViewModel
{
    /**
     * @param  list<BonusShopItem>  $items
     */
    public function __construct(
        public string $sitename,
        public SafeHtml $msg,
        public string $bonus,
        public string $lockText,
        public array $items,
    ) {}
}
