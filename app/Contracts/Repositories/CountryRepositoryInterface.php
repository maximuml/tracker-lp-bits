<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface CountryRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array;
}
