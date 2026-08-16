<?php
namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\HitAndRunMode;
use App\Enums\ModelEventEnum;
use App\Models\HitAndRun;
use App\Models\Message;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class HitAndRunRepository extends BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function getList(array $params)
    {
        $query = HitAndRun::query()->with(['user', 'torrent', 'snatch']);
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['uid'])) {
            $query->where('uid', $params['uid']);
        }
        if (!empty($params['torrent_id'])) {
            $query->where('torrent_id', $params['torrent_id']);
        }
        if (!empty($params['username'])) {
            $query->whereHas('user', function (Builder $query) use ($params) {
                return $query->where('username', $params['username']);
            });
        }
        $query->orderBy('id', 'desc');
        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  \App\Models\HitAndRun
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
     * @param  int  $id
     * @return  \App\Models\HitAndRun
     */
    public function update(array $params, int $id): HitAndRun
    {
        $model = HitAndRun::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $model->update($data);
        return $model;
    }

    /**
     * @param  int  $id
     * @return  \App\Models\HitAndRun
     */
    public function getDetail(int $id): HitAndRun
    {
        $model = HitAndRun::query()->with(['user', 'torrent', 'snatch'])->findOrFail($id);
        return $model;
    }

    /**
     * @param  int  $id
     * @return  bool
     */
    public function delete(int $id): bool
    {
        $model = HitAndRun::query()->findOrFail($id);
        $result = $model->delete();
        HitAndRun::clearCache($model, ModelEventEnum::HIT_AND_RUN_DELETED);
        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  \App\Models\User  $user
     * @return  mixed
     */
    public function bulkDelete(array $params, User $user)
    {
        $baseQuery = $this->getBulkQuery($params);
        $list = $baseQuery->clone()->get();
        if ($list->isEmpty()) {
            return 0;
        }
        $result = $baseQuery->delete();
        \App\Support\Logger::writeWithContext((string) sprintf('user: %s bulk delete by filter: %s, result: %s', $user->id, json_encode($params), json_encode($result)), (string) 'alert', (bool) false);
        if ($result) {
            foreach ($list as $record) {
                if (!$record instanceof HitAndRun) {
                    continue;
                }
                HitAndRun::clearCache($record, ModelEventEnum::HIT_AND_RUN_DELETED);
            }
        }
        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  \Illuminate\Database\Eloquent\Builder<HitAndRun>
     */
    private function getBulkQuery(array $params): Builder
    {
        $query = HitAndRun::query();
        $hasWhere = false;
        $validFilter = ['uid', 'id'];
        foreach ($validFilter as $item) {
            if (!empty($params[$item])) {
                $hasWhere = true;
                $query->whereIn($item, Arr::wrap($params[$item]));
            }
        }
        if (!$hasWhere) {
            throw new \InvalidArgumentException("No filter.");
        }
        return $query;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $ignoreTime
     * @return  mixed
     */
    public function cronjobUpdateStatus($uid = null, $torrentId = null, $ignoreTime = false)
    {
        $diffInSection = HitAndRun::diffInSection();
        $browseMode = \App\Support\Config\SiteConfig::current()->main->browseCat();
        $setting = HitAndRun::getConfig('*', $browseMode);
        if (HitAndRunMode::fromStringSafe($setting['mode'] ?? null)->isEnabled()) {
            $setting['diff_in_section'] = $diffInSection;
            $setting['search_box_id'] = $browseMode;
            $this->doCronjobUpdateStatus($setting, $uid, $torrentId, $ignoreTime);
            $this->checkAndDisableUser($setting);
        }
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $ignoreTime
     * @return  mixed
     */
    private function doCronjobUpdateStatus(array $setting, $uid = null, $torrentId = null, $ignoreTime = false)
    {
        \App\Support\Logger::writeWithContext((string) ("setting: " . json_encode($setting) . ", uid: {$uid}, torrentId: {$torrentId}, ignoreTime: " . var_export($ignoreTime, true)), (string) 'info', (bool) false);
        $size = 1000;
        $page = 1;
        $mode = HitAndRunMode::fromStringSafe($setting['mode'] ?? null);
        if ($mode === HitAndRunMode::DISABLED) {
            \App\Support\Logger::writeWithContext((string) "H&R mode is disabled.", (string) 'info', (bool) false);
            return false;
        }
        if (empty($setting['inspect_time'])) {
            \App\Support\Logger::writeWithContext((string) "H&R inspect_time is not set.", (string) 'info', (bool) false);
            return false;
        }
        $query = HitAndRun::query()
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->with([
                'torrent' => function ($query) {$query->select(['id', 'size', 'name', 'category']);},
                'snatch',
                'user' => function ($query) {$query->select(['id', 'username', 'lang', 'class', 'donoruntil', 'enabled', 'notifs']);},
                'user.language',
            ]);
        if (!is_null($uid)) {
            $query->where('uid', $uid);
        }
        if (!is_null($torrentId)) {
            $query->where('torrent_id', $torrentId);
        }
        if (!$ignoreTime) {
            $query->where('created_at', '<', Carbon::now()->subHours($setting['inspect_time']));
        }
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }

        $successCounts = 0;
        $disabledUsers = [];
        while (true) {
            $logPrefix = "page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            \App\Support\Logger::writeWithContext((string) ("{$logPrefix}, counts: " . $rows->count()), (string) 'info', (bool) false);
            if ($rows->isEmpty()) {
                \App\Support\Logger::writeWithContext((string) ("{$logPrefix}, no more data..." . \App\Support\LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                break;
            }
            foreach ($rows as $row) {
                $currentLog = "$logPrefix, [HANDLING] " . $row->toJson();
                \App\Support\Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);
                if (!$row->user) {
                    \App\Support\Logger::writeWithContext((string) "{$currentLog}, user not exists, remove it!", (string) 'error', (bool) false);
                    $row->delete();
                    continue;
                }
                if (!$row->snatch) {
                    \App\Support\Logger::writeWithContext((string) "{$currentLog}, snatch not exists, skip!", (string) 'error', (bool) false);
                    continue;
                }
                if (!$row->torrent) {
                    \App\Support\Logger::writeWithContext((string) "{$currentLog}, torrent not exists, remove it!", (string) 'error', (bool) false);
                    $row->delete();
                    continue;
                }

                //If is VIP or above OR donated, pass
                if ($row->user->class >= HitAndRun::MINIMUM_IGNORE_USER_CLASS || $row->user->isDonating()) {
                    $result = $this->reachedBySpecialUserClass($row);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //check seed time
                $targetSeedTime = $row->snatch->seedtime;
                $requireSeedTime = bcmul((string) (float) $setting['seed_time_minimum'], '3600');
                \App\Support\Logger::writeWithContext((string) "{$currentLog}, targetSeedTime: {$targetSeedTime}, requireSeedTime: {$requireSeedTime}", (string) 'info', (bool) false);
                if ($targetSeedTime >= $requireSeedTime) {
                    $result = $this->reachedBySeedTime($row, $setting);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //check leech time
                if (isset($setting['leech_time_minimum']) && $setting['leech_time_minimum'] > 0) {
                    //use diff, other index should do also, update later @todo
                    $targetLeechTime = $row->snatch->leech_time_no_seeder - $row->leech_time_no_seeder_begin;
                    $requireLeechTime = bcmul((string) (float) $setting['leech_time_minimum'], '3600');
                    \App\Support\Logger::writeWithContext((string) "{$currentLog}, targetLeechTime: {$targetLeechTime}, requireLeechTime: {$requireLeechTime}", (string) 'info', (bool) false);
                    if ($targetLeechTime >= $requireLeechTime) {
                        $result = $this->reachedByLeechTime($row, $setting);
                        if ($result) {
                            $successCounts++;
                        }
                        continue;
                    }
                }

                //check share ratio
                $targetShareRatio = bcdiv((string)$row->snatch->uploaded, (string)$row->torrent->size, 4);
                $requireShareRatio = $setting['ignore_when_ratio_reach'];
                \App\Support\Logger::writeWithContext((string) "{$currentLog}, targetShareRatio: {$targetShareRatio}, requireShareRatio: {$requireShareRatio}", (string) 'info', (bool) false);
                if ($targetShareRatio >= $requireShareRatio) {
                    $result = $this->reachedByShareRatio($row, $setting);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //unreached
                if ($row->created_at->addHours((int)$setting['inspect_time'])->lte(Carbon::now())) {
                    $result = $this->unreached($row, $setting, !isset($disabledUsers[$row->uid]));
                    if ($result) {
                        $successCounts++;
                        $disabledUsers[$row->uid] = true;
                    }
                }
            }
            $page++;
        }
        \App\Support\Logger::writeWithContext((string) "[CRONJOB_UPDATE_HR_DONE]", (string) 'info', (bool) false);
        return $successCounts;
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @return  array<int|string, mixed>
     */
    private function geReachedMessage(HitAndRun $hitAndRun): array
    {
        $snatched = $hitAndRun->snatch;
        return [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => \App\Support\Locale::trans('hr.reached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $hitAndRun->user->locale),
            'msg' => \App\Support\Locale::trans('hr.reached_message_content', ['completed_at' => \App\Support\Time::formatDateTime($snatched->completedat ?: $snatched->startdat), 'torrent_id' => $hitAndRun->torrent_id, 'torrent_name' => $hitAndRun->torrent->name], $hitAndRun->user->locale),
        ];
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @param  array<int|string, mixed>  $setting
     */
    private function reachedByShareRatio(HitAndRun $hitAndRun, array $setting): bool
    {
        \App\Support\Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $comment = \App\Support\Locale::trans('hr.reached_by_share_ratio_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time_minimum' => $setting['seed_time_minimum'], 'seed_time' => bcdiv((string) $hitAndRun->snatch->seedtime, '3600', 1), 'share_ratio' => \App\Support\Ratio::hr($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded), 'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach']], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @param  array<int|string, mixed>  $setting
     */
    private function reachedBySeedTime(HitAndRun $hitAndRun, array $setting): bool
    {
        \App\Support\Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $comment = \App\Support\Locale::trans('hr.reached_by_seed_time_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time' => bcdiv((string) $hitAndRun->snatch->seedtime, '3600', 1), 'seed_time_minimum' => $setting['seed_time_minimum']], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @param  array<int|string, mixed>  $setting
     */
    private function reachedByLeechTime(HitAndRun $hitAndRun, array $setting): bool
    {
        \App\Support\Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $comment = \App\Support\Locale::trans('hr.reached_by_leech_time_comment', ['now' => Carbon::now()->toDateTimeString(), 'leech_time' => bcdiv((string) ($hitAndRun->snatch->leech_time_no_seeder - $hitAndRun->leech_time_no_seeder_begin), '3600', 1), 'leech_time_minimum' => $setting['leech_time_minimum']], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    /** @param  \App\Models\HitAndRun  $hitAndRun */
    private function reachedBySpecialUserClass(HitAndRun $hitAndRun): bool
    {
        \App\Support\Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $comment = \App\Support\Locale::trans('hr.reached_by_special_user_class_comment', ['user_class_text' => $hitAndRun->user->class_text], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @param  array<string, mixed>  $update
     * @param  string  $logPrefix
     */
    private function inspectingToReached(HitAndRun $hitAndRun, array $update, string $logPrefix = ''): bool
    {
        $update['status'] = HitAndRun::STATUS_REACHED;
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->update($update);
        \App\Support\Logger::writeWithContext((string) ("[{$logPrefix}], " . \App\Support\LegacyDb::lastQuery(false, 'json') . ", affectedRows: {$affectedRows}"), (string) 'info', (bool) false);
        if ($affectedRows != 1) {
            \App\Support\Logger::writeWithContext((string) ($hitAndRun->toJson() . ", [{$logPrefix}], affectedRows != 1, skip!"), (string) 'notice', (bool) false);
            return false;
        }
        if ($hitAndRun->user->acceptNotification('hr_reached')) {
            $message = $this->geReachedMessage($hitAndRun);
            Message::query()->insert($message);
        } else {
            \App\Support\Logger::writeWithContext((string) ($hitAndRun->toJson() . ", [{$logPrefix}], user do not accept hr_reached notification"), (string) 'notice', (bool) false);
        }
        HitAndRun::clearCache($hitAndRun);
        return true;
    }

    /**
     * @param  \App\Models\HitAndRun  $hitAndRun
     * @param  array<int|string, mixed>  $setting
     * @param  bool  $disableUser
     */
    private function unreached(HitAndRun $hitAndRun, array $setting, bool $disableUser = true): bool
    {
        \App\Support\Logger::writeWithContext((string) sprintf('hitAndRun: %s, disableUser: %s', $hitAndRun->toJson(), var_export($disableUser, true)), (string) 'info', (bool) false);
        $comment = \App\Support\Locale::trans('hr.unreached_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time' => bcdiv((string) $hitAndRun->snatch->seedtime, '3600', 1), 'seed_time_minimum' => $setting['seed_time_minimum'], 'share_ratio' => \App\Support\Ratio::hr($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded), 'torrent_size' => \App\Support\Format::size($hitAndRun->torrent->size), 'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach']], $hitAndRun->user->locale);
        $update = [
            'status' => HitAndRun::STATUS_UNREACHED,
            'comment' => $comment
        ];
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->update($update);
        \App\Support\Logger::writeWithContext((string) ("[H&R_UNREACHED], " . \App\Support\LegacyDb::lastQuery(false, 'json') . ", affectedRows: {$affectedRows}"), (string) 'info', (bool) false);
        if ($affectedRows != 1) {
            \App\Support\Logger::writeWithContext((string) ($hitAndRun->toJson() . ", [H&R_UNREACHED], affectedRows != 1, skip!"), (string) 'notice', (bool) false);
            return false;
        }
        $message = [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => \App\Support\Locale::trans('hr.unreached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $hitAndRun->user->locale),
            'msg' => \App\Support\Locale::trans('hr.unreached_message_content', ['completed_at' => \App\Support\Time::formatDateTime($hitAndRun->snatch->completedat), 'torrent_id' => $hitAndRun->torrent_id, 'torrent_name' => $hitAndRun->torrent->name], $hitAndRun->user->locale),
        ];
        Message::query()->insert($message);
        HitAndRun::clearCache($hitAndRun);
        return true;
    }

    /** @param  array<int|string, mixed>  $setting */
    private function checkAndDisableUser(array $setting): void
    {
        $logPrefix = "setting: " . json_encode($setting);
        $disableCounts = HitAndRun::getConfig('ban_user_when_counts_reach', $setting['search_box_id']);
        if ($disableCounts <= 0) {
            \App\Support\Logger::writeWithContext((string) "{$logPrefix}, disableCounts: {$disableCounts} <= 0, invalid, return", (string) 'error', (bool) false);
            return;
        }
        $query = HitAndRun::query()
            ->selectRaw("count(*) as counts, uid")
            ->where('status', HitAndRun::STATUS_UNREACHED)
            ->groupBy('uid')
            ->havingRaw("count(*) >= $disableCounts")
        ;
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }
        $result = $query->get();
        if ($result->isEmpty()) {
            \App\Support\Logger::writeWithContext((string) ("{$logPrefix}, No user to disable: " . \App\Support\LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
            return;
        }
        $users = User::query()
            ->with('language')
            ->where('class', '<', User::CLASS_VIP)
            ->where('enabled', User::ENABLED_YES)
            ->where('donor', 'no')
            ->find($result->pluck('uid')->toArray(), ['id', 'username', 'lang']);
        \App\Support\Logger::writeWithContext((string) ("{$logPrefix}, Going to disable user: " . json_encode($users->toArray())), (string) 'info', (bool) false);
        foreach ($users as $user) {
            $locale = $user->locale;
            $comment = \App\Support\Locale::trans('hr.unreached_disable_comment', [], $locale);
            $user->updateWithModComment(['enabled' => User::ENABLED_NO], sprintf('%s - %s', date('Y-m-d'), $comment));
            $message = [
                'receiver' => $user->id,
                'added' => Carbon::now()->toDateTimeString(),
                'subject' => $comment,
                'msg' => \App\Support\Locale::trans('hr.unreached_disable_message_content', ['ban_user_when_counts_reach' => $disableCounts], $locale),
            ];
            Message::query()->insert($message);
            $userBanLog = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => $comment
            ];
            UserBanLog::query()->insert($userBanLog);
            \App\Support\Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
            \App\Support\Logger::writeWithContext((string) ("Disable user: " . \App\Support\Json::encode($userBanLog)), (string) 'info', (bool) false);
        }
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $formatted
     * @return  mixed
     */
    public function getStatusStats($uid, $formatted = true)
    {
        $diffInSection = HitAndRun::diffInSection();
        if ($diffInSection) {
            $query = NexusDB::table('hit_and_runs')
                ->leftJoin('torrents', 'torrents.id', '=', 'hit_and_runs.torrent_id')
                ->leftJoin('categories', 'categories.id', '=', 'torrents.category')
                ->where('hit_and_runs.uid', $uid)
                ->select('hit_and_runs.status', 'categories.mode', NexusDB::raw('count(*) as counts'))
                ->groupBy('hit_and_runs.status', 'categories.mode');
        } else {
            $query = NexusDB::table('hit_and_runs')
                ->where('uid', $uid)
                ->select('status', NexusDB::raw('count(*) as counts'))
                ->groupBy('status');
        }
        $results = $query->get()->map(fn ($row) => (array) $row)->all();
        \App\Support\Logger::writeWithContext((string) ("user: {$uid}, sql: " . $query->toSql() . ", results: " . json_encode($results)), (string) 'info', (bool) false);
        if (!$formatted) {
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
                    $grouped[$info['mode']][HitAndRun::STATUS_INSPECTING] ?? 0,
                    $grouped[$info['mode']][HitAndRun::STATUS_UNREACHED] ?? 0,
                    HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                );
            }
            return implode(" ", $out);
        } else {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['status']] = $item['counts'];
            }
            foreach (SearchBox::listSections() as $key => $info) {
                if ($key == SearchBox::SECTION_BROWSE) {
                    return sprintf(
                        '%s/<font color="red">%s</font>/%s',
                        $grouped[HitAndRun::STATUS_INSPECTING] ?? 0,
                        $grouped[HitAndRun::STATUS_UNREACHED] ?? 0,
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
            $results[] = ['status' => $key, 'text' => \App\Support\Locale::trans('hr.status_' . $key, [], null)];
        }
        return $results;
    }

    /**
     * @param  int  $id
     * @param  \App\Models\User  $user
     */
    public function pardon(int $id, User $user): bool
    {
        $model = HitAndRun::query()->findOrFail($id);
        if (!in_array($model->status, $this->getCanPardonStatus())) {
            throw new \LogicException("Can't be pardoned due to status is: " . $model->status_text . " !");
        }
        $model->status = HitAndRun::STATUS_PARDONED;
        $prefix = date('Y-m-d') . ' - Pardon by ' . $user->username;
        $existing = (string) $model->comment;
        $model->comment = $existing === '' ? $prefix : "\n" . $prefix . $existing;
        $model->save();
        return true;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  \App\Models\User  $user
     */
    public function bulkPardon(array $params, User $user): int
    {
        $baseQuery = $this->getBulkQuery($params)->whereIn('status', $this->getCanPardonStatus());
        $list = $baseQuery->clone()->get();
        if ($list->isEmpty()) {
            return 0;
        }
        $prefix = 'Pardon by ' . $user->username;
        $ids = $list->pluck('id')->map('intval')->all();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $affected = DB::update(
            "UPDATE hit_and_runs SET status = ?, updated_at = ?, comment = CASE WHEN comment = '' THEN ? ELSE CONCAT('\\n', ?, comment) END WHERE id IN ({$placeholders})",
            array_merge([HitAndRun::STATUS_PARDONED, Carbon::now()->toDateTimeString(), $prefix, $prefix], $ids)
        );
        \App\Support\Logger::writeWithContext((string) sprintf('user: %s bulk pardon by filter: %s, affected: %s', $user->id, json_encode($params), $affected), (string) 'alert', (bool) false);
        if ($affected) {
            foreach ($list as $item) {
                if (!$item instanceof HitAndRun) {
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
            return \App\Support\Html::tr('H&R', $hrRadio, 1, "mode_$searchBoxId", true);
        }
        return '';
    }
}
