<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

/**
 * The `[allowed]`/`[denied]`/`[pending]` marker appended to an offer
 * title — label plus the nx-color-* span class.
 */
final class OfferAllowedBadge
{
    public function __construct(
        public readonly string $label,
        public readonly string $cssClass,
    ) {}
}
