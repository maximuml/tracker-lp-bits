<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\SearchBox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SearchBoxRepositoryInterface
{
    public function getAllRows(): array;

    public function getTaxonomyRows(string $tableName, int $mode);

    public function getTaxonomyList(string $tableName, int $mode): array;

    public function getList(array $params): LengthAwarePaginator;

    public function store(array $params);

    public function update(array $params, int $id);

    public function getDetail(int $id);

    public function delete(int $id);

    public function listIcon(array $idArr);

    public function migrateToModeRelated();

    public function listSections($id, $withCategoryAndTags = true);

    public function getOrderedIds(): array;

    public function findForCategoryTable(string|int $mode): SearchBox;

    public function getCategoriesForTable(SearchBox $searchBox, bool $selectUnselect = false): Collection;
}
