<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Latest forum posts table section on the index page.
 */
final readonly class IndexForumPostsSection
{
    /**
     * @param  list<IndexForumPostItem>  $items
     */
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public string $colTopicTitle = '',
        public string $colView = '',
        public string $colAuthor = '',
        public string $colPostedAt = '',
        public string $textIn = '',
        public array $items = [],
    ) {}
}
