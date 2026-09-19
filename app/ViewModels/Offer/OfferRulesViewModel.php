<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;

/**
 * Data for the offers rules panel — the former `<ul>` rules block
 * concatenated in OfferPageService::buildOfferList().
 */
final class OfferRulesViewModel
{
    public function __construct(
        public readonly SafeHtml $uploadClassName,
        public readonly SafeHtml $addofferClassName,
        public readonly ?SafeHtml $skipApprovedText,
        public readonly int $minVotes,
        public readonly bool $showVoteTimeout,
        public readonly int $voteTimeoutHours,
        public readonly bool $showUpTimeout,
        public readonly int $upTimeoutHours,
    ) {}
}
