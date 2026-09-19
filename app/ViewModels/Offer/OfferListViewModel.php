<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;

/**
 * The offers list section: rules panel, add-offer link, search box and
 * the listing table (or the "nothing found" empty state).
 */
final class OfferListViewModel
{
    /**
     * @param  list<OfferCategoryOption>  $categories
     */
    public function __construct(
        public readonly OfferRulesViewModel $rules,
        public readonly bool $canAddOffer,
        public readonly array $categories,
        public readonly ?OfferTableViewModel $table,
        public readonly SafeHtml $emptyState,
        public readonly int $count,
    ) {}
}
