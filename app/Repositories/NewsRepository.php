<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\News;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsRepository extends BaseRepository
{
    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'userid', 'added'];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = News::query()->with(['user']);
        if (! empty($params['userid'])) {
            $query->where('userid', $params['userid']);
        }
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        /** @var array<string, mixed> $params */
        $model = News::query()->create($params);

        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return mixed
     */
    public function update(array $params, $id)
    {
        $model = News::query()->findOrFail((int) $id);
        /** @var array<string, mixed> $params */
        $model->update($params);

        return $model;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getDetail($id)
    {
        $model = News::query()->findOrFail((int) $id);

        return $model;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id)
    {
        $model = News::query()->findOrFail((int) $id);
        $result = $model->delete();

        return $result;
    }

    public function deleteById(int $id): int
    {
        return News::query()->where('id', $id)->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertGetId(array $attributes): int
    {
        return (int) News::query()->insertGetId($attributes);
    }

    public function findById(int $id): ?News
    {
        return News::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateFields(int $id, array $fields): int
    {
        return News::query()->where('id', $id)->update($fields);
    }

    /**
     * @return LengthAwarePaginator<int, News>
     */
    public function paginateLatest(int $perPage)
    {
        return News::query()->with(['user'])->latest('added')->paginate($perPage);
    }

    public function countAddedAfter(string $datetime): int
    {
        return News::query()->where('added', '>', $datetime)->count();
    }
}
