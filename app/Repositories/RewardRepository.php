<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Reward;
use Carbon\Carbon;

class RewardRepository extends BaseRepository
{
    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'torrentid', 'userid', 'value', 'added'];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = Reward::query()->with(['user']);
        if (! empty($params['torrent_id'])) {
            $query->where('torrentid', (int) $params['torrent_id']);
        }
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function update(array $params, int $id)
    {
        $model = Reward::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $model->update($data);

        return $model;
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id)
    {
        $model = Reward::query()->findOrFail($id);

        return $model;
    }

    /**
     * @return mixed
     */
    public function delete(int $id)
    {
        $model = Reward::query()->findOrFail($id);
        $result = $model->delete();

        return $result;
    }

    public function countSince(int $userId, Carbon $since): int
    {
        return Reward::query()
            ->where('userid', $userId)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
