<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface StyleRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function row(int|string $id): ?array;

    public function uri(int|string $id): ?string;

    public function highlightColor(int|string $id): ?string;

    public function firstId(): ?int;
}
