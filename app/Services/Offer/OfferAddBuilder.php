<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Support\Category;
use App\ViewModels\Offer\OfferCategoryOption;

/**
 * Builds the "add offer" form data (category options + empty body).
 */
final class OfferAddBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(mixed $browsecatmode): array
    {
        $typeOptions = [];
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $typeOptions[] = new OfferCategoryOption((int) $rowArr['id'], (string) $rowArr['name']);
        }

        return [
            'typeOptions' => $typeOptions,
            'bodyContent' => '',
        ];
    }
}
