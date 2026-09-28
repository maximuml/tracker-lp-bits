<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Top uploaders (all-time / last-30-days tabs) section on the index page.
 */
final readonly class IndexTopUploadersSection
{
    /**
     * @param  list<IndexTopUploaderRow>  $allRows
     * @param  list<IndexTopUploaderRow>  $recentRows
     */
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public string $toggleHint = '',
        public string $recentlyLabel = '',
        public string $allLabel = '',
        public string $colAuthor = '',
        public string $colCounts = '',
        public string $colRanking = '',
        public array $allRows = [],
        public array $recentRows = [],
    ) {}
}
