<?php

declare(strict_types=1);

namespace App\ViewModels\Search;

/**
 * One cell of the search-box category/taxonomy checkbox grid —
 * either a labelled checkbox (category icon link or taxonomy name)
 * or the select/unselect-all button cell (`kind === 'selectAll'`).
 * Replaces the per-cell markup strings of `SearchBox::buildCategoryTable()`.
 */
final class SearchCategoryCell
{
    private function __construct(
        public readonly string $kind,
        public readonly string $checkPrefix,
        public readonly string $inputName,
        public readonly string $value,
        public readonly bool $checked,
        public readonly ?string $iconSrc,
        public readonly ?string $iconClass,
        public readonly string $label,
        public readonly ?string $href,
    ) {}

    public static function category(
        int|string $id,
        string $value,
        bool $checked,
        string $iconSrc,
        string $iconClass,
        string $name,
        string $href,
    ): self {
        return new self('category', 'cat', 'cat'.$id, $value, $checked, $iconSrc, $iconClass, $name, $href);
    }

    public static function taxonomy(
        string $inputName,
        string $value,
        bool $checked,
        string $name,
        ?string $href,
    ): self {
        return new self('taxonomy', '', $inputName, $value, $checked, null, null, $name, $href);
    }

    public static function selectAll(string $checkPrefix): self
    {
        return new self('selectAll', $checkPrefix, '', '', false, null, null, '', null);
    }
}
