<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;
use App\ViewModels\Torrent\CategoryIcon;

/**
 * One row of the offers listing table.
 */
final class OfferRow
{
    public function __construct(
        public readonly int $id,
        public readonly CategoryIcon $categoryIcon,
        public readonly string $displayName,
        public readonly string $fullName,
        public readonly bool $isNew,
        public readonly OfferAllowedBadge $allowed,
        public readonly ?OfferVoteResults $voteResults,
        public readonly OfferCommentCell $comment,
        public readonly SafeHtml $addedTime,
        public readonly SafeHtml $timeout,
        public readonly SafeHtml $offeredBy,
    ) {}
}
