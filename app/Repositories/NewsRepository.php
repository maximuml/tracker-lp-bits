<?php
namespace App\Repositories;

use App\Models\News;

class NewsRepository extends BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function getList(array $params)
    {
        $query = News::query()->with(['user']);
        if (!empty($params['userid'])) {
            $query->where('userid', $params['userid']);
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
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
     * @return  mixed
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
     * @return  mixed
     */
    public function getDetail($id)
    {
        $model = News::query()->findOrFail((int) $id);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function delete($id)
    {
        $model = News::query()->findOrFail((int) $id);
        $result = $model->delete();
        return $result;
    }
}
