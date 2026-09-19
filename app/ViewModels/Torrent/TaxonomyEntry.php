<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * One taxonomy label/value pair shown next to the category name on the
 * details page (`<b>{label}: </b>{value}`).
 */
final class TaxonomyEntry
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}
}
