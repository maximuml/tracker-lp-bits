<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Category;
use App\Models\SearchBox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SearchBoxRepositoryInterface
{
    /**
     * Lightweight id+name list for select dropdowns.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function listIdName(): \Illuminate\Support\Collection;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllRows(): array;

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function getTaxonomyRows(string $tableName, int $mode);

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTaxonomyList(string $tableName, int $mode): array;

    /**
     * @param  array<int|string, mixed>  $params
     * @return LengthAwarePaginator<int, SearchBox>
     */
    public function getList(array $params): LengthAwarePaginator;

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params);

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function update(array $params, int $id);

    /**
     * @return mixed
     */
    public function getDetail(int $id);

    /**
     * @return mixed
     */
    public function delete(int $id);

    /**
     * @param  array<int|string, mixed>  $idArr
     * @return mixed
     */
    public function listIcon(array $idArr);

    /**
     * @return mixed
     */
    public function migrateToModeRelated();

    /**
     * @param  array<int>|int  $id
     * @param  bool  $withCategoryAndTags
     * @return Collection<int, SearchBox>
     */
    public function listSections($id, $withCategoryAndTags = true);

    /**
     * @return list<int>
     */
    public function getOrderedIds(): array;

    public function findForCategoryTable(string|int $mode): SearchBox;

    /**
     * @return Collection<int, Category>
     */
    public function getCategoriesForTable(SearchBox $searchBox, bool $selectUnselect = false): Collection;

    /**
     * @param  array<int>|int  $id
     * @return mixed
     */
    public function deleteCategory($id);
}
