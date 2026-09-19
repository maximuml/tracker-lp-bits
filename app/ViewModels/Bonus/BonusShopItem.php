<?php

declare(strict_types=1);

namespace App\ViewModels\Bonus;

use App\Support\Html\SafeHtml;

/**
 * One row of the karma shop table. `name`, `description` and
 * `pointsLabel` are trusted markup (lang strings carry `<span>`/`<br>`/
 * `<b>`; the gift rows embed tax notes) — the template renders them
 * verbatim. Extra form fields per `art` (`title`, `gift_1`, `gift_2`,
 * `cancel_hr`) are owned by the `my.sections.bonus_shop` template, not
 * the service.
 */
final readonly class BonusShopItem
{
    public function __construct(
        public int $index,
        public string $art,
        public SafeHtml $name,
        public SafeHtml $description,
        public SafeHtml $pointsLabel,
        public BonusTradeButton $trade,
    ) {}
}
