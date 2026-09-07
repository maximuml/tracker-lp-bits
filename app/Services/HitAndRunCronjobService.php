<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HitAndRunMode;
use App\Enums\HitAndRunStatus;
use App\Enums\UserClass as UserClassEnum;
use App\Events\UserUpdated;
use App\Models\HitAndRun;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBanLog;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HitAndRunCronjobService
{
    public function __construct(
        private HitAndRunStatusService $statusService,
    ) {}

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $ignoreTime
     * @return mixed
     */
    public function cronjobUpdateStatus($uid = null, $torrentId = null, $ignoreTime = false)
    {
        $diffInSection = HitAndRun::diffInSection();
        $browseMode = SiteConfig::current()->main->browseCat();
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
     * @return mixed
     */
    private function doCronjobUpdateStatus(array $setting, $uid = null, $torrentId = null, $ignoreTime = false)
    {
        Logger::writeWithContext((string) ('setting: '.json_encode($setting).", uid: {$uid}, torrentId: {$torrentId}, ignoreTime: ".var_export($ignoreTime, true)), (string) 'info', (bool) false);
        $size = 1000;
        $page = 1;
        $mode = HitAndRunMode::fromStringSafe($setting['mode'] ?? null);
        if ($mode === HitAndRunMode::DISABLED) {
            Logger::writeWithContext((string) 'H&R mode is disabled.', (string) 'info', (bool) false);

            return false;
        }
        if (empty($setting['inspect_time'])) {
            Logger::writeWithContext((string) 'H&R inspect_time is not set.', (string) 'info', (bool) false);

            return false;
        }
        $query = HitAndRun::query()
            ->where('status', HitAndRunStatus::INSPECTING->value)
            ->with([
                'torrent' => function ($query) {
                    $query->select(['id', 'size', 'name', 'category']);
                },
                'snatch',
                'user' => function ($query) {
                    $query->select(['id', 'username', 'lang', 'class', 'donoruntil', 'enabled', 'notifs']);
                },
                'user.language',
            ]);
        if ($uid !== null) {
            $query->where('uid', $uid);
        }
        if ($torrentId !== null) {
            $query->where('torrent_id', $torrentId);
        }
        if (! $ignoreTime) {
            $query->where('created_at', '<', Carbon::now()->subHours($setting['inspect_time']));
        }
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }

        $successCounts = 0;
        $disabledUsers = [];
        $messages = [];
        DB::beginTransaction();
        while (true) {
            $logPrefix = "page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            Logger::writeWithContext((string) ("{$logPrefix}, counts: ".$rows->count()), (string) 'info', (bool) false);
            if ($rows->isEmpty()) {
                Logger::writeWithContext((string) ("{$logPrefix}, no more data...".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);
                break;
            }
            foreach ($rows as $row) {
                $currentLog = "$logPrefix, [HANDLING] ".$row->toJson();
                Logger::writeWithContext((string) $logPrefix, (string) 'info', (bool) false);
                if (! $row->user) {
                    Logger::writeWithContext((string) "{$currentLog}, user not exists, remove it!", (string) 'error', (bool) false);
                    $row->delete();

                    continue;
                }
                if (! $row->snatch) {
                    Logger::writeWithContext((string) "{$currentLog}, snatch not exists, skip!", (string) 'error', (bool) false);

                    continue;
                }
                if (! $row->torrent) {
                    Logger::writeWithContext((string) "{$currentLog}, torrent not exists, remove it!", (string) 'error', (bool) false);
                    $row->delete();

                    continue;
                }

                // If is VIP or above OR donated, pass
                if ($row->user->class >= HitAndRun::MINIMUM_IGNORE_USER_CLASS || $row->user->isDonating()) {
                    $result = $this->statusService->reachedBySpecialUserClass($row, $messages);
                    if ($result) {
                        $successCounts++;
                    }

                    continue;
                }

                // check seed time
                $targetSeedTime = $row->snatch->seedtime;
                $requireSeedTime = bcmul((string) (float) $setting['seed_time_minimum'], '3600');
                Logger::writeWithContext((string) "{$currentLog}, targetSeedTime: {$targetSeedTime}, requireSeedTime: {$requireSeedTime}", (string) 'info', (bool) false);
                if ($targetSeedTime >= $requireSeedTime) {
                    $result = $this->statusService->reachedBySeedTime($row, $setting, $messages);
                    if ($result) {
                        $successCounts++;
                    }

                    continue;
                }

                // check leech time
                if (isset($setting['leech_time_minimum']) && $setting['leech_time_minimum'] > 0) {
                    // use diff, other index should do also, update later @todo
                    $targetLeechTime = $row->snatch->leech_time_no_seeder - $row->leech_time_no_seeder_begin;
                    $requireLeechTime = bcmul((string) (float) $setting['leech_time_minimum'], '3600');
                    Logger::writeWithContext((string) "{$currentLog}, targetLeechTime: {$targetLeechTime}, requireLeechTime: {$requireLeechTime}", (string) 'info', (bool) false);
                    if ($targetLeechTime >= $requireLeechTime) {
                        $result = $this->statusService->reachedByLeechTime($row, $setting, $messages);
                        if ($result) {
                            $successCounts++;
                        }

                        continue;
                    }
                }

                // check share ratio
                $targetShareRatio = bcdiv((string) $row->snatch->uploaded, (string) $row->torrent->size, 4);
                $requireShareRatio = $setting['ignore_when_ratio_reach'];
                Logger::writeWithContext((string) "{$currentLog}, targetShareRatio: {$targetShareRatio}, requireShareRatio: {$requireShareRatio}", (string) 'info', (bool) false);
                if ($targetShareRatio >= $requireShareRatio) {
                    $result = $this->statusService->reachedByShareRatio($row, $setting, $messages);
                    if ($result) {
                        $successCounts++;
                    }

                    continue;
                }

                // unreached
                if ($row->created_at->addHours((int) $setting['inspect_time'])->lte(Carbon::now())) {
                    $result = $this->statusService->unreached($row, $setting, ! isset($disabledUsers[$row->uid]), $messages);
                    if ($result) {
                        $successCounts++;
                        $disabledUsers[$row->uid] = true;
                    }
                }
            }
            $page++;
        }
        DB::commit();
        if (! empty($messages)) {
            Message::query()->insert($messages);
        }
        Logger::writeWithContext((string) '[CRONJOB_UPDATE_HR_DONE]', (string) 'info', (bool) false);

        return $successCounts;
    }

    /** @param  array<int|string, mixed>  $setting */
    private function checkAndDisableUser(array $setting): void
    {
        $logPrefix = 'setting: '.json_encode($setting);
        $disableCounts = HitAndRun::getConfig('ban_user_when_counts_reach', $setting['search_box_id']);
        if ($disableCounts <= 0) {
            Logger::writeWithContext((string) "{$logPrefix}, disableCounts: {$disableCounts} <= 0, invalid, return", (string) 'error', (bool) false);

            return;
        }
        $query = HitAndRun::query()
            ->selectRaw('count(*) as counts, uid')
            ->where('status', HitAndRunStatus::UNREACHED->value)
            ->groupBy('uid')
            ->havingRaw('count(*) >= ?', [$disableCounts]);
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }
        $result = $query->get();
        if ($result->isEmpty()) {
            Logger::writeWithContext((string) ("{$logPrefix}, No user to disable: ".LegacyDb::lastQuery(false, 'json')), (string) 'info', (bool) false);

            return;
        }
        $users = User::query()
            ->with('language')
            ->where('class', '<', UserClassEnum::VIP->value)
            ->where('enabled', true)
            ->where('donor', false)
            ->find($result->pluck('uid')->toArray(), ['id', 'username', 'lang']);
        Logger::writeWithContext((string) ("{$logPrefix}, Going to disable user: ".json_encode($users->toArray())), (string) 'info', (bool) false);
        foreach ($users as $user) {
            $locale = $user->locale;
            $comment = Locale::trans('hr.unreached_disable_comment', [], $locale);
            $user->updateWithModComment(['enabled' => false], sprintf('%s - %s', date('Y-m-d'), $comment));
            $message = [
                'receiver' => $user->id,
                'added' => Carbon::now()->toDateTimeString(),
                'subject' => $comment,
                'msg' => Locale::trans('hr.unreached_disable_message_content', ['ban_user_when_counts_reach' => $disableCounts], $locale),
            ];
            Message::query()->insert($message);
            $userBanLog = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => $comment,
            ];
            UserBanLog::query()->insert($userBanLog);
            event(new UserUpdated($user));
            Logger::writeWithContext((string) ('Disable user: '.Json::encode($userBanLog)), (string) 'info', (bool) false);
        }
    }
}
