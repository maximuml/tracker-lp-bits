<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Enums\HitAndRunMode;
use App\Enums\ModelEventEnum;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Events;
use App\Support\LegacyDb;
use App\Support\Logger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class HitAndRunHandler
{
    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $torrent
     * @param  array<string, mixed>|false  $snatchInfo
     * @return array<string, mixed>|null
     */
    public function handle(
        int $left,
        ?string $event,
        array $user,
        array $torrent,
        int $userId,
        int $torrentId,
        bool $isDonor,
        string $dt,
        array|false $snatchInfo,
    ): ?array {
        if (($left <= 0 && $event !== 'completed')
            || (int) $user['class'] >= (int) User::CLASS_VIP
            || $isDonor
            || empty($torrent['mode'])
        ) {
            return null;
        }

        $snatchInfo = LegacyDb::snatchInfo($torrentId, $userId);
        if (! $snatchInfo) {
            return null;
        }

        $hrMode = HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $torrent['mode'])) ? $mode : null
        );
        Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}", (string) 'info', (bool) false);

        if (! $hrMode->isGlobal() && ($hrMode !== HitAndRunMode::MANUAL || $torrent['hr'] != Torrent::HR_YES)) {
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}, not match", (string) 'debug', (bool) false);

            return $snatchInfo;
        }

        $hrCacheKey = HitAndRun::getCacheKey($userId, $torrentId);
        $hrExists = Cache::remember($hrCacheKey, mt_rand(86400, 86400 * 3), function () use ($userId, $torrentId) {
            $record = HitAndRun::query()->where('uid', $userId)->where('torrent_id', $torrentId)->first();

            return $record ? $record->toJson() : false;
        });

        if ($hrExists) {
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, already exists", (string) 'debug', (bool) false);

            return $snatchInfo;
        }

        $includeRate = (float) HitAndRun::getConfig('include_rate', $torrent['mode']);
        $requiredDownloaded = (int) $torrent['size'] * $includeRate;

        Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, includeRate: {$includeRate}, requiredDownloaded: {$requiredDownloaded}, snatchDownloaded: {$snatchInfo['downloaded']}", (string) 'info', (bool) false);

        if ((int) $snatchInfo['downloaded'] >= $requiredDownloaded) {
            $hrRecord = [
                'uid' => $userId,
                'torrent_id' => $torrentId,
                'snatched_id' => $snatchInfo['id'],
                'created_at' => $dt,
                'updated_at' => $dt,
            ];

            $affectedRows = DB::table('hit_and_runs')->insertOrIgnore($hrRecord);
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} >= required: {$requiredDownloaded}, [INSERT_H&R], affectedRows: {$affectedRows}", (string) 'info', (bool) false);

            if ($affectedRows > 0) {
                $hitAndRunRecord = HitAndRun::query()->where('uid', $userId)->where('torrent_id', $torrentId)->first();
                if ($hitAndRunRecord) {
                    DB::table('snatched')->where('id', (int) $snatchInfo['id'])->update(['hit_and_run_id' => $hitAndRunRecord->id]);
                    Events::fire(ModelEventEnum::HIT_AND_RUN_CREATED, $hitAndRunRecord, null);
                }
            }
        } else {
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} < required: {$requiredDownloaded}", (string) 'debug', (bool) false);
        }

        return $snatchInfo;
    }
}
