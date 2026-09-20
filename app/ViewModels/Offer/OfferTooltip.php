<?php

declare(strict_types=1);

namespace App\ViewModels\Offer;

use App\Support\Html\SafeHtml;

/**
 * One hidden tooltip div — the data counterpart of a
 * `Tag::tooltipContainer()` item.
 */
final class OfferTooltip
{
    public function __construct(
        public readonly string $id,
        public readonly SafeHtml $content,
    ) {}
}
