<?php

declare(strict_types=1);

namespace App\Services\Announce;

final class TrafficAccountant
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $torrent
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $self
     * @param array<string, mixed>|false $snatchInfo
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

        $upthis = max(0, (int) \bcsub((string) $params['uploaded'], (string) $self['uploaded']));
        $downthis = max(0, (int) \bcsub((string) $params['downloaded'], (string) $self['downloaded']));
        $snatchTimeColumn = $self['seeder'] === 'yes' ? 'seedtime' : 'leechtime';
        $snatchTimeIncrement = max(0, (int) $self['announcetime']);

        $leechTimeNoSeederIncrement = 0;
        if ((int) $torrent['seeders'] <= 0 && $seeder === 'no' && $snatchTimeIncrement > 0) {
            $leechTimeNoSeederIncrement = $snatchTimeIncrement;
        }

        $uploadedIncrementForUser = 0;
        $downloadedIncrementForUser = 0;
        if ($upthis > 0 || $downthis > 0) {
            $queries = $params;
            $queries['ip'] = $ip;
            $promotionInfo = apply_filter('torrent_promotion', $torrent);
            $dataTraffic = getDataTraffic($torrent, $queries, $user, $self, $snatchInfo ?: [], $promotionInfo);
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
