<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

/**
 * The comments cell: `0` links to the add-comment form, otherwise a bold
 * count link carrying either a `title` (showlastcom off) or a
 * `data-domtt-src` tooltip reference (showlastcom on).
 */
final class OfferCommentCell
{
    public function __construct(
        public readonly int $count,
        public readonly string $href,
        public readonly bool $hasNew,
        public readonly ?string $title,
        public readonly ?string $tooltipId,
    ) {}
}
