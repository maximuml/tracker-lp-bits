<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the faqmanage page (faqmanage/index.blade.php).
 *
 * Wraps InfoRepository::faqManageData() output.
 */
final class FaqmanagePageViewModel extends ViewModel
{
    /**
     * @param  array<int|string, mixed>  $faqCateg
     * @param  array<int|string, mixed>  $faqOrphaned
     */
    public function __construct(
        public readonly array $faqCateg,
        public readonly array $faqOrphaned,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['faqCateg', 'faqOrphaned'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
