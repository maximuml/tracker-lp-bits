<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Recent news section on the index page.
 */
final readonly class IndexNewsSection
{
    /**
     * @param  list<IndexNewsItem>  $items
     */
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public bool $canManage = false,
        public string $manageLink = '',
        public array $items = [],
        public string $showHideTitle = '',
        public string $editLabel = '',
        public string $deleteLabel = '',
    ) {}
}
