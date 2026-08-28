<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Reward;
use App\Models\Torrent;
use App\Models\User;
use App\Support\LegacyDb;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;

class RewardRepository extends BaseRepository
{
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
     * @return mixed
     */
    public function store(int $torrentId, float $value, User $user)
    {
        if ($user->seedbonus < $value) {
            throw new \LogicException('your bonus not enough.');
        }
        if ($user->reward_torrent_logs()->where('torrentid', $torrentId)->exists()) {
            throw new \LogicException('you already reward this torrent.');
        }
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();
        $torrentOwner = User::query()->findOrFail($torrent->owner);
        if ($user->id == $torrentOwner->id) {
            throw new \LogicException("you can't reward to yourself.");
        }
        $torrentOwner->checkIsNormal();

        return DB::transaction(function () use ($torrentId, $value, $user, $torrentOwner) {
            $model = $user->reward_torrent_logs()->create([
                'torrentid' => $torrentId,
                'value' => $value,
            ]);
            $affectedRows = User::query()
                ->where('id', $user->id)
                ->where('seedbonus', $user->seedbonus)
                ->decrement('seedbonus', $value);
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ("affectedRows: {$affectedRows}, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                throw new \RuntimeException('decrement user bonus fail.');
            }
            $affectedRows = User::query()
                ->where('id', $torrentOwner->id)
                ->where('seedbonus', $torrentOwner->seedbonus)
                ->increment('seedbonus', $value);
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ("affectedRows: {$affectedRows}, query: ".LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                throw new \RuntimeException('increment owner bonus fail.');
            }

            return $model;
        });
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
}
