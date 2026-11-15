<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the faqactions page (faqactions/index.blade.php).
 *
 * Assembled by FaqController::faqactions(); covers edit, additem,
 * addsection and confirm_delete modes.
 */
final class FaqactionsPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $arr
     * @param  array<int|string, mixed>  $categories
     * @param  array<int|string, mixed>  $languages
     */
    public function __construct(
        public readonly string $mode,
        public readonly array $arr = [],
        public readonly array $categories = [],
        public readonly int $inid = 0,
        public readonly int $langid = 0,
        public readonly array $languages = [],
        public readonly string $deflang = '',
        public readonly int $id = 0,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['mode', 'arr', 'categories', 'inid', 'langid', 'languages', 'deflang', 'id'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
