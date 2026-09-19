<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;

/**
 * The offers listing table — sort-link header urls, typed rows, hidden
 * domTT tooltips and the bottom pager.
 */
final class OfferTableViewModel
{
    /**
     * @param  list<OfferRow>  $rows
     * @param  list<OfferTooltip>  $tooltips
     */
    public function __construct(
        public readonly string $sortCatUrl,
        public readonly string $sortNameUrl,
        public readonly string $sortVResUrl,
        public readonly string $sortCommentsUrl,
        public readonly string $sortAddedUrl,
        public readonly bool $showTimeout,
        public readonly bool $canManage,
        public readonly bool $canAgainst,
        public readonly bool $showAgainstCell,
        public readonly array $rows,
        public readonly array $tooltips,
        public readonly SafeHtml $pagerBottom,
    ) {}
}
