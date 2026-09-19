<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

/**
 * The "yeah - against = total" vote-results cell. Null on the row means
 * both counters are zero and the cell renders a plain `0`.
 */
final class OfferVoteResults
{
    public function __construct(
        public readonly int $yeah,
        public readonly int $against,
        public readonly string $href,
    ) {}
}
