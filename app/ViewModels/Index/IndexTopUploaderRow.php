<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

use App\Support\Html\SafeHtml;

/**
 * One ranked uploader row on the index page.
 */
final readonly class IndexTopUploaderRow
{
    public function __construct(
        public SafeHtml $username,
        public int $count,
        public int $rank,
    ) {}
}
