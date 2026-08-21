<?php

namespace App\Repositories;

use App\Models\AgentDeny;

class AgentDenyRepository extends BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = AgentDeny::query()->with(['family']);
        if (! empty($params['family_id'])) {
            $query->where('family_id', $params['family_id']);
        }
        $query->orderBy('family_id', 'desc');

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        /** @var array<string, mixed> $params */
        $model = AgentDeny::query()->create($params);

        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return mixed
     */
    public function update(array $params, $id)
    {
        $model = AgentDeny::query()->findOrFail((int) $id);
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
        $model = AgentDeny::query()->findOrFail((int) $id);

        return $model;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id)
    {
        $model = AgentDeny::query()->findOrFail((int) $id);
        $result = $model->delete();

        return $result;
    }
}
