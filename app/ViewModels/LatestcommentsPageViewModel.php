<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the latestcomments page (latestcomments/index.blade.php).
 *
 * Assembled by ForumController::latestComments(); dead keys pagertop/pagerbottom/offset/perpage dropped.
 */
final class LatestcommentsPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $rows = null,
        public readonly mixed $count = null,
        public readonly mixed $pager = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['rows', 'count', 'pager'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
