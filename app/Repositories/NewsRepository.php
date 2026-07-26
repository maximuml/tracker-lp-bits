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
        $model = News::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function getDetail($id)
    {
        $model = News::query()->findOrFail($id);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function delete($id)
    {
        $model = News::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }
}
