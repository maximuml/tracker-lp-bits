<?php

declare(strict_types=1);

namespace App\ViewModels\HitAndRun;

use App\Models\User;
use App\ViewModels\ViewModel;

/**
 * ViewModel for the member H&R page (my/hr.blade.php).
 *
 * Assembled by MyController::hr(). `page` is the domain VM built by
 * HitAndRunPageService; the rest is the page chrome contract the blade
 * reads (status filter nav, search query, pager).
 */
final class MyHrPageViewModel extends ViewModel
{
    /**
     * @param  list<array{query: string, active: bool, text: string, status: int}>  $headerFilters
     * @param  array{page?: int, pages?: int, href?: string, prevLabel?: string, nextLabel?: string, zeroBased?: bool}  $pager
     */
    public function __construct(
        public readonly User $userInfo,
        public readonly int $userid,
        public readonly string $status,
        public readonly array $headerFilters,
        public readonly string $queryString,
        public readonly string $q,
        public readonly int $rescount,
        public readonly array $pager,
        public readonly HitAndRunPageViewModel $page,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
