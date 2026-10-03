<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface ToptenRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function page(int $type, int $limit, ?string $subtype): array;
}
