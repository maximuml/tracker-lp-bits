<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\HitAndRunMode;
use App\Enums\HitAndRunStatus;
use App\Enums\ModelEventEnum;
use App\Models\HitAndRun;
use App\Models\SearchBox;
use App\Models\User;
use App\Services\HitAndRunCronjobService;
use App\Support\Html;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HitAndRunRepository extends BaseRepository
{
    public function __construct(
        private HitAndRunCronjobService $cronjobService,
    ) {}

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = HitAndRun::query()->with(['user', 'torrent', 'snatch']);
        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (! empty($params['uid'])) {
            $query->where('uid', $params['uid']);
        }
        if (! empty($params['torrent_id'])) {
            $query->where('torrent_id', $params['torrent_id']);
        }
        if (! empty($params['username'])) {
            $query->whereHas('user', function (Builder $query) use ($params) {
                return $query->where('username', $params['username']);
            });
        }
        $query->orderBy('id', 'desc');

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function store(array $params): HitAndRun
    {
        /** @var array<string, mixed> $data */
        $data = $params;
        $model = HitAndRun::query()->create($data);

        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function update(array $params, int $id): HitAndRun
    {
        $model = HitAndRun::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $model->update($data);

        return $model;
    }

    public function getDetail(int $id): HitAndRun
    {
        $model = HitAndRun::query()->with(['user', 'torrent', 'snatch'])->findOrFail($id);

        return $model;
    }

    public function delete(int $id): bool
    {
        $model = HitAndRun::query()->findOrFail($id);
        $result = $model->delete();
        HitAndRun::clearCache($model, ModelEventEnum::HIT_AND_RUN_DELETED);

        return $result ?? true;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function bulkDelete(array $params, User $user)
    {
        $baseQuery = $this->getBulkQuery($params);
        $list = $baseQuery->clone()->get();
        if ($list->isEmpty()) {
            return 0;
        }
        $result = $baseQuery->delete();
        Logger::writeWithContext((string) sprintf('user: %s bulk delete by filter: %s, result: %s', $user->id, json_encode($params), json_encode($result)), (string) 'alert', (bool) false);
        if ($result) {
            foreach ($list as $record) {
                if (! $record instanceof HitAndRun) {
                    continue;
                }
                HitAndRun::clearCache($record, ModelEventEnum::HIT_AND_RUN_DELETED);
            }
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return Builder<HitAndRun>
     */
    private function getBulkQuery(array $params): Builder
    {
        $query = HitAndRun::query();
        $hasWhere = false;
        $validFilter = ['uid', 'id'];
        foreach ($validFilter as $item) {
            if (! empty($params[$item])) {
                $hasWhere = true;
                $query->whereIn($item, Arr::wrap($params[$item]));
            }
        }
        if (! $hasWhere) {
            throw new \InvalidArgumentException('No filter.');
        }

        return $query;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $ignoreTime
     * @return mixed
     */
    public function cronjobUpdateStatus($uid = null, $torrentId = null, $ignoreTime = false)
    {
        return $this->cronjobService->cronjobUpdateStatus($uid, $torrentId, $ignoreTime);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $formatted
     * @return mixed
     */
    public function getStatusStats($uid, $formatted = true)
    {
        $diffInSection = HitAndRun::diffInSection();
        if ($diffInSection) {
            $query = DB::table('hit_and_runs')
                ->leftJoin('torrents', 'torrents.id', '=', 'hit_and_runs.torrent_id')
                ->leftJoin('categories', 'categories.id', '=', 'torrents.category')
                ->where('hit_and_runs.uid', $uid)
                ->select('hit_and_runs.status', 'categories.mode', DB::raw('count(*) as counts'))
                ->groupBy('hit_and_runs.status', 'categories.mode');
        } else {
            $query = DB::table('hit_and_runs')
                ->where('uid', $uid)
                ->select('status', DB::raw('count(*) as counts'))
                ->groupBy('status');
        }
        $results = $query->get()->map(fn ($row) => (array) $row)->all();
        Logger::writeWithContext((string) ("user: {$uid}, sql: ".$query->toSql().', results: '.json_encode($results)), (string) 'info', (bool) false);
        if (! $formatted) {
            return $results;
        }
        if ($diffInSection) {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['mode']][$item['status']] = $item['counts'];
            }
            $out = [];
            foreach (SearchBox::listSections() as $key => $info) {
                $out[] = sprintf(
                    '%s: %s/<font color="red">%s</font>/%s',
                    $info['text'],
                    $grouped[$info['mode']][HitAndRunStatus::INSPECTING->value] ?? 0,
                    $grouped[$info['mode']][HitAndRunStatus::UNREACHED->value] ?? 0,
                    HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                );
            }

            return implode(' ', $out);
        } else {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['status']] = $item['counts'];
            }
            foreach (SearchBox::listSections() as $key => $info) {
                if ($key == SearchBox::SECTION_BROWSE) {
                    return sprintf(
                        '%s/<font color="red">%s</font>/%s',
                        $grouped[HitAndRunStatus::INSPECTING->value] ?? 0,
                        $grouped[HitAndRunStatus::UNREACHED->value] ?? 0,
                        HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                    );
                }
            }
        }
    }

    /** @return  array<int|string, mixed> */
    public function listStatus(): array
    {
        $results = [];
        foreach (HitAndRun::$status as $key => $value) {
            $results[] = ['status' => $key, 'text' => Locale::trans('hr.status_'.$key, [], null)];
        }

        return $results;
    }

    public function pardon(int $id, User $user): bool
    {
        $model = HitAndRun::query()->findOrFail($id);
        if (! in_array($model->status, $this->getCanPardonStatus())) {
            throw new \LogicException("Can't be pardoned due to status is: ".$model->status_text.' !');
        }
        $model->status = HitAndRunStatus::PARDONED->value;
        $prefix = date('Y-m-d').' - Pardon by '.$user->username;
        $existing = (string) $model->comment;
        $model->comment = $existing === '' ? $prefix : "\n".$prefix.$existing;
        $model->save();

        return true;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    public function bulkPardon(array $params, User $user): int
    {
        $baseQuery = $this->getBulkQuery($params)->whereIn('status', $this->getCanPardonStatus());
        $list = $baseQuery->clone()->get();
        if ($list->isEmpty()) {
            return 0;
        }
        $prefix = 'Pardon by '.$user->username;
        $ids = $list->pluck('id')->map('intval')->all();
        $affected = DB::table('hit_and_runs')
            ->whereIn('id', $ids)
            ->update([
                'status' => HitAndRunStatus::PARDONED->value,
                'updated_at' => Carbon::now()->toDateTimeString(),
                'comment' => DB::raw("CASE WHEN comment = '' THEN ".DB::getPdo()->quote($prefix)." ELSE CONCAT('\\n', ".DB::getPdo()->quote($prefix).', comment) END'), // @phpstan-ignore argument.type
            ]);
        Logger::writeWithContext((string) sprintf('user: %s bulk pardon by filter: %s, affected: %s', $user->id, json_encode($params), $affected), (string) 'alert', (bool) false);
        if ($affected) {
            foreach ($list as $item) {
                if (! $item instanceof HitAndRun) {
                    continue;
                }
                HitAndRun::clearCache($item);
            }
        }

        return $affected;
    }

    /** @return  array<int|string, mixed> */
    private function getCanPardonStatus(): array
    {
        return HitAndRun::CAN_PARDON_STATUS;
    }

    /**
     * @param  mixed  $value
     * @param  mixed  $searchBoxId
     */
    public function renderOnUploadPage($value, $searchBoxId): string
    {
        if (HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $searchBoxId)) ? $mode : null
        ) === HitAndRunMode::MANUAL && Permission::canSetTorrentHitAndRun()) {
            $hrRadio = sprintf('<label><input type="radio" name="hr[%s]" value="0"%s />NO</label>', $searchBoxId, $value == 0 ? ' checked' : '');
            $hrRadio .= sprintf('<label><input type="radio" name="hr[%s]" value="1"%s />YES</label>', $searchBoxId, $value == 1 ? ' checked' : '');

            return (string) Html::tr('H&R', $hrRadio, 1, "mode_$searchBoxId", true);
        }

        return '';
    }
}
