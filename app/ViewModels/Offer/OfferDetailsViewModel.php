<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;

/**
 * The offer details section (off_details action): info/status/vote rows,
 * action links, comments block and the quick-reply form.
 */
final class OfferDetailsViewModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly SafeHtml $offeredBy,
        public readonly SafeHtml $offerTime,
        public readonly OfferAllowedBadge $status,
        public readonly bool $showAllowRow,
        public readonly bool $isPending,
        public readonly bool $canAgainst,
        public readonly int $yeah,
        public readonly int $against,
        public readonly string $allowedNote,
        public readonly bool $showEditDelete,
        public readonly SafeHtml $description,
        public readonly int $commentCount,
        public readonly SafeHtml $pagerTop,
        public readonly SafeHtml $pagerBottom,
    ) {}
}
