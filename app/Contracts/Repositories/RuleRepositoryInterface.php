<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Site rules (`rules` table) CRUD for the modrules staff page.
 */
interface RuleRepositoryInterface
{
    /** @param  array<string, mixed>  $data */
    public function insert(array $data): void;

    /** @param  array<string, mixed>  $data */
    public function updateById(int $id, array $data): void;

    public function deleteById(int $id): void;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function listAllWithLang(): array;
}
