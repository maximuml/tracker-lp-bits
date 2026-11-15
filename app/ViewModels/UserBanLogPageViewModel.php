<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the user-ban-log page (user-ban-log/index.blade.php).
 *
 * Assembled by AdminToolsController::userBanLog(); dead keys paginationTop/paginationBottom dropped.
 */
final class UserBanLogPageViewModel extends ViewModel
{
    /**
     * @param  array<string, string>  $header
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $pager
     */
    public function __construct(
        public readonly string $q,
        public readonly array $header,
        public readonly array $rows,
        public readonly array $pager,
        public readonly string $serverRequestUri,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['q', 'header', 'rows', 'pager', 'serverRequestUri'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
