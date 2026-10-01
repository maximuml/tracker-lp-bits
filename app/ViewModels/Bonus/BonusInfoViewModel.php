<?php

declare(strict_types=1);

namespace App\ViewModels\Bonus;

use App\Support\Html\SafeHtml;

/**
 * The "what is karma" info section of the mybonus page — replaces the
 * second ob_start/echo block in `BonusPageService::buildInfoSection()`.
 *
 * Numbers/flags only; the lang strings rendered by the template carry
 * their own markup (`text_bonus_formula_*` interleave `</li><li>` and
 * `<img>` tags), so the template wraps them in SafeHtml where needed.
 * `summaryTable` stays trusted markup — it is produced by
 * `Bonus::buildBonusTableForUser()` which is shared with other callers.
 */
final readonly class BonusInfoViewModel
{
    public function __construct(
        public float $perseedingBonus,
        public int $maxseedingBonus,
        public float $tzeroBonus,
        public float $nzeroBonus,
        public float $zeroBonusFactor,
        public float $bzeroBonus,
        public float $lBonus,
        public ?string $minSizeLine,
        public float $donortimesBonus,
        public string $currentSeedBonus,
        public string $aFactor,
        public string $percentLabel,
        public string $loadbarClass,
        public int $userId,
        public ?string $officialAdditionFactor,
        public ?string $haremAdditionFactor,
        public SafeHtml $summaryTable,
        public float $uploadtorrentBonus,
        public float $starttopicBonus,
        public float $makepostBonus,
        public float $addcommentBonus,
        public float $pollvoteBonus,
        public float $offervoteBonus,
        public float $saythanksBonus,
        public float $receivethanksBonus,
        public float $ratiolimitBonus,
        public int $dlamountlimitBonus,
    ) {}
}
