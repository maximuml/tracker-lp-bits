<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface StyleRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return Collection<int, \stdClass>
     */
    public function listOrderedByName(): Collection;

    /**
     * Fresh read of the whole stylesheets table, bypassing the
     * per-process memo (and updating it).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function row(int|string $id): ?array;

    public function uri(int|string $id): ?string;

    public function highlightColor(int|string $id): ?string;

    public function firstId(): ?int;
}
