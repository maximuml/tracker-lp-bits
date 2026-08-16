<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Enums\HitAndRunMode;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\User;
use App\Support\LegacyDb;
use Nexus\Database\NexusDB;

final class HitAndRunHandler
{
    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $torrent
     * @param array<string, mixed>|false $snatchInfo
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
        if (!$snatchInfo) {
            return null;
        }

        $hrMode = HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $torrent['mode'])) ? $mode : null
        );
        \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}", (string) 'info', (bool) false);

        if (! $hrMode->isGlobal() && ($hrMode !== HitAndRunMode::MANUAL || $torrent['hr'] != Torrent::HR_YES)) {
            \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}, not match", (string) 'debug', (bool) false);
            return $snatchInfo;
        }

        $hrCacheKey = HitAndRun::getCacheKey($userId, $torrentId);
        $hrExists = NexusDB::remember($hrCacheKey, mt_rand(86400, 86400 * 3), function () use ($userId, $torrentId) {
            $record = HitAndRun::query()->where('uid', $userId)->where('torrent_id', $torrentId)->first();
            return $record ? $record->toJson() : null;
        });

        if ($hrExists) {
            \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, already exists", (string) 'debug', (bool) false);
            return $snatchInfo;
        }

        $includeRate = (float) HitAndRun::getConfig('include_rate', $torrent['mode']);
        $requiredDownloaded = (int) $torrent['size'] * $includeRate;

        \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, includeRate: {$includeRate}, requiredDownloaded: {$requiredDownloaded}, snatchDownloaded: {$snatchInfo['downloaded']}", (string) 'info', (bool) false);

        if ((int) $snatchInfo['downloaded'] >= $requiredDownloaded) {
            $hrRecord = [
                'uid'         => $userId,
                'torrent_id'  => $torrentId,
                'snatched_id' => $snatchInfo['id'],
                'created_at'  => $dt,
                'updated_at'  => $dt,
            ];

            $affectedRows = NexusDB::table('hit_and_runs')->insertOrIgnore($hrRecord);
            \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} >= required: {$requiredDownloaded}, [INSERT_H&R], affectedRows: {$affectedRows}", (string) 'info', (bool) false);

            if ($affectedRows > 0) {
                $hitAndRunRecord = HitAndRun::query()->where('uid', $userId)->where('torrent_id', $torrentId)->first();
                if ($hitAndRunRecord) {
                    NexusDB::table('snatched')->where('id', (int) $snatchInfo['id'])->update(['hit_and_run_id' => $hitAndRunRecord->id]);
                    \App\Support\Events::fire(\App\Enums\ModelEventEnum::HIT_AND_RUN_CREATED, $hitAndRunRecord, null);
                }
            }
        } else {
            \App\Support\Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} < required: {$requiredDownloaded}", (string) 'debug', (bool) false);
        }

        return $snatchInfo;
    }
}
