<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the userhistory page (userhistory/index.blade.php).
 *
 * Assembled by InfoController::userhistory(); repo result keys the view never reads (posts/comments/editorNames/commentPageMap) are dropped by fromPageData().
 */
final class UserhistoryPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $pager
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly string $action,
        public readonly int $userid,
        public readonly mixed $subject,
        public readonly string $subjectName,
        public readonly string $title,
        public readonly int $postcount = 0,
        public readonly int $commentcount = 0,
        public readonly int $perpage = 15,
        public readonly array $pager = [],
        public readonly array $items = [],
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['action', 'userid', 'subject', 'subjectName', 'title', 'postcount', 'commentcount', 'perpage', 'pager', 'items'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
