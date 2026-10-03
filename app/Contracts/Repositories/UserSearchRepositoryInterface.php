<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface UserSearchRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{count: int, rows: array<int, array<string, mixed>>, q: string}
     */
    public function administrativeSearch(array $params, bool $hasModcomment, int $perPage = 30): array;
}
