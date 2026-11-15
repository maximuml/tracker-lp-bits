<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the bonus-log page (bonus-log/index.blade.php).
 *
 * Assembled by BonusHistoryController::bonusLog(); dead keys pagertop/pager dropped.
 */
final class BonusLogPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $title = null,
        public readonly mixed $uid = null,
        public readonly mixed $username = null,
        public readonly mixed $category = null,
        public readonly mixed $businessType = null,
        public readonly mixed $categoryText = null,
        public readonly mixed $businessTypeText = null,
        public readonly mixed $textSelectOnePlease = null,
        public readonly mixed $resetText = null,
        public readonly mixed $submitText = null,
        public readonly mixed $categoryOptionList = null,
        public readonly mixed $businessTypeOptionList = null,
        public readonly mixed $pagerParam = null,
        public readonly mixed $pagerbottom = null,
        public readonly mixed $rows = null,
        public readonly mixed $requestUri = null,
        public readonly mixed $columnBusinessTypeLabel = null,
        public readonly mixed $columnOldTotalLabel = null,
        public readonly mixed $columnValueLabel = null,
        public readonly mixed $columnNewTotalLabel = null,
        public readonly mixed $columnCommentLabel = null,
        public readonly mixed $columnCreatedAtLabel = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['title', 'uid', 'username', 'category', 'businessType', 'categoryText', 'businessTypeText', 'textSelectOnePlease', 'resetText', 'submitText', 'categoryOptionList', 'businessTypeOptionList', 'pagerParam', 'pagerbottom', 'rows', 'requestUri', 'columnBusinessTypeLabel', 'columnOldTotalLabel', 'columnValueLabel', 'columnNewTotalLabel', 'columnCommentLabel', 'columnCreatedAtLabel'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
