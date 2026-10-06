<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Enums\HitAndRunMode;
use App\Enums\TorrentHr;
use App\Enums\UserClass as UserClassEnum;
use App\Events\HitAndRunCreated;
use App\Models\HitAndRun;
use App\Repositories\HitAndRunLookupRepository;
use App\Repositories\SnatchRepository;
use App\Services\TorrentStatsService;
use App\Support\Logger;
use App\Support\RedisGuard;
use Illuminate\Support\Facades\Cache;

final class HitAndRunHandler
{
    public function __construct(
        private readonly HitAndRunLookupRepository $hitAndRunRepository,
        private readonly SnatchRepository $snatchRepository,
        private readonly TorrentStatsService $torrentStats,
    ) {}

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
            || (int) $user['class'] >= (int) UserClassEnum::VIP->value
            || $isDonor
            || empty($torrent['mode'])
        ) {
            return null;
        }

        $snatchInfo = $this->torrentStats->getSnatchInfo($torrentId, $userId);
        if (! $snatchInfo) {
            return null;
        }

        $hrMode = HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $torrent['mode'])) ? $mode : null
        );
        Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}", (string) 'info', (bool) false);

        if (! $hrMode->isGlobal() && ($hrMode !== HitAndRunMode::MANUAL || $torrent['hr'] != TorrentHr::YES->value)) {
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, hrMode: {$hrMode->value}, not match", (string) 'debug', (bool) false);

            return $snatchInfo;
        }

        $hrCacheKey = HitAndRun::getCacheKey($userId, $torrentId);
        $lookupHr = function () use ($userId, $torrentId) {
            $record = $this->hitAndRunRepository->findByUidTorrent($userId, $torrentId);

            return $record ? $record->toJson() : false;
        };
        $hrExists = RedisGuard::attempt(
            static fn () => Cache::remember($hrCacheKey, random_int(86400, 86400 * 3), $lookupHr),
        ) ?? $lookupHr();

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

            $affectedRows = $this->hitAndRunRepository->insertOrIgnore($hrRecord);
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} >= required: {$requiredDownloaded}, [INSERT_H&R], affectedRows: {$affectedRows}", (string) 'info', (bool) false);

            if ($affectedRows > 0) {
                $hitAndRunRecord = $this->hitAndRunRepository->findByUidTorrent($userId, $torrentId);
                if ($hitAndRunRecord) {
                    $this->snatchRepository->linkHitAndRun((int) $snatchInfo['id'], (int) $hitAndRunRecord->id);
                    event(new HitAndRunCreated($hitAndRunRecord));
                }
            }
        } else {
            Logger::writeWithContext((string) "[HR_LOG] user: {$userId}, torrent: {$torrentId}, total downloaded: {$snatchInfo['downloaded']} < required: {$requiredDownloaded}", (string) 'debug', (bool) false);
        }

        return $snatchInfo;
    }
}
