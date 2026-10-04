<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface SiteLogRepositoryInterface
{
    public function create(string $text, string $security = 'normal', ?int $userId = null): void;

    public function deleteBefore(string $before): int;
}
