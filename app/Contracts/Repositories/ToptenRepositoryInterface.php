<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface ToptenRepositoryInterface
{
    /**
     * @return array{type: 1|2|3|5|6, limit: int<1, 250>, subtype: ?string}
     */
    public function normalize(int $type, int $limit, ?string $subtype): array;

    /**
     * @return array<string, mixed>
     */
    public function page(int $type, int $limit, ?string $subtype): array;
}
