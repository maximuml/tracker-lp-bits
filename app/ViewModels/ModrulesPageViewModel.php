<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the modrules page (modrules/index.blade.php).
 *
 * Assembled by StaffModerationController::modrules(); covers list, newsect and edit modes.
 */
final class ModrulesPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int|string, mixed>  $langs
     * @param  array<string, mixed>  $rule
     */
    public function __construct(
        public readonly string $mode,
        public readonly array $rows = [],
        public readonly array $langs = [],
        public readonly string $deflang = '',
        public readonly array $rule = [],
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['mode', 'rows', 'langs', 'deflang', 'rule'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
