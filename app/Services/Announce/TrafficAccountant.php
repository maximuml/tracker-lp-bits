<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Support\Hooks;
use App\Support\TorrentOps;

final class TrafficAccountant
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $torrent
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>|null  $self
     * @param  array<string, mixed>|false  $snatchInfo
     */
    public function calculate(
        ?array $self,
        array $params,
        array $torrent,
        array $user,
        array|false $snatchInfo,
        string $ip,
        string $seeder,
    ): TrafficResult {
        if ($self === null) {
            return new TrafficResult(0, 0, null, 0, 0, 0, 0);
        }

        $snatchTimeColumn = ($self['seeder'] ?? '') === 'yes' ? 'seedtime' : 'leechtime';
        $snatchTimeIncrement = max(0, (int) ($self['announcetime'] ?? 0));

        $rawUploaded = $params['uploaded'] ?? 0;
        $rawSelfUploaded = $self['uploaded'] ?? 0;
        $rawDownloaded = $params['downloaded'] ?? 0;
        $rawSelfDownloaded = $self['downloaded'] ?? 0;

        if (! is_numeric($rawUploaded) || ! is_numeric($rawSelfUploaded) || ! is_numeric($rawDownloaded) || ! is_numeric($rawSelfDownloaded)) {
            return new TrafficResult(0, 0, $snatchTimeColumn, $snatchTimeIncrement, 0, 0, 0);
        }

        /** @var numeric-string $uploaded */
        $uploaded = (string) $rawUploaded;
        /** @var numeric-string $selfUploaded */
        $selfUploaded = (string) $rawSelfUploaded;
        /** @var numeric-string $downloaded */
        $downloaded = (string) $rawDownloaded;
        /** @var numeric-string $selfDownloaded */
        $selfDownloaded = (string) $rawSelfDownloaded;

        $upthis = max(0, (int) \bcsub($uploaded, $selfUploaded));
        $downthis = max(0, (int) \bcsub($downloaded, $selfDownloaded));

        $leechTimeNoSeederIncrement = 0;
        if ((int) $torrent['seeders'] <= 0 && $seeder === 'no' && $snatchTimeIncrement > 0) {
            $leechTimeNoSeederIncrement = $snatchTimeIncrement;
        }

        $uploadedIncrementForUser = 0;
        $downloadedIncrementForUser = 0;
        if ($upthis > 0 || $downthis > 0) {
            $queries = $params;
            $queries['ip'] = $ip;
            $promotionInfo = Hooks::applyFilter('torrent_promotion', $torrent);
            $dataTraffic = TorrentOps::dataTraffic($torrent, $queries, $user, $self, $snatchInfo ?: [], $promotionInfo);
            $uploadedIncrementForUser = (int) $dataTraffic['uploaded_increment_for_user'];
            $downloadedIncrementForUser = (int) $dataTraffic['downloaded_increment_for_user'];
        }

        return new TrafficResult(
            $upthis,
            $downthis,
            $snatchTimeColumn,
            $snatchTimeIncrement,
            $leechTimeNoSeederIncrement,
            $uploadedIncrementForUser,
            $downloadedIncrementForUser,
        );
    }
}
