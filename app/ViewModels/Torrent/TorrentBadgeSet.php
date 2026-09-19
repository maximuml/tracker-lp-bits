<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * The badge cluster rendered after a torrent name: paid icon, promotion
 * badge + its "will end in" suffix, hit-and-run marker, approval status.
 * Replaces the concatenated HTML string in `TorrentListRow::$badges`.
 */
final class TorrentBadgeSet
{
    public function __construct(
        public readonly bool $paid = false,
        public readonly ?PromotionBadge $promotion = null,
        public readonly bool $hitAndRun = false,
        public readonly ?ApprovalBadge $approval = null,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->paid
            && $this->promotion === null
            && ! $this->hitAndRun
            && $this->approval === null;
    }
}
