<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * A category/second-icon sprite cell for the torrents table —
 * `<img class="{iconClass}" src="pic/cattrans.gif">`, optionally wrapped
 * in a category-filter link. Replaces `Category::imageTag()`/
 * `secondIcon()` HTML strings.
 */
final class CategoryIcon
{
    public function __construct(
        public readonly string $iconClass,
        public readonly string $name,
        public readonly ?string $href = null,
    ) {}
}
