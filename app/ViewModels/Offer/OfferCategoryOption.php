<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

/**
 * One `<option>` in the offer search category dropdown.
 */
final class OfferCategoryOption
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
