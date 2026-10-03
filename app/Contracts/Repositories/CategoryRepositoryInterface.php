<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface CategoryRepositoryInterface
{
    public function tableNameForType(string $type): string;

    /**
     * @return array<string, mixed>|null
     */
    public function getRecord(string $table, int $id): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIconRows(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryRows(): array;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function findSecondIcon(array $row): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriesByMode(int $catmode): array;
}
