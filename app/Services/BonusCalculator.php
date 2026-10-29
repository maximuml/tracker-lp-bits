<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure per-hour seed-bonus math drained out of `calculate_seed_bonus()`:
 * given already fetched torrent rows and resolved settings it reproduces
 * the legacy formula bit for bit. No I/O — fetch/orchestration lives in
 * {@see SeedBonusService}.
 */
final class BonusCalculator
{
    /**
     * Aggregate the per-torrent seeding contributions into the bonus
     * result array consumed by callers (SeedBonusJob job,
     * TrackerCalculateSeedBonus command, ClaimRepository).
     *
     * @param  array<int, array<string, mixed>>  $torrentRows  Rows with
     *                                                         keys: id, added, size, seeders, last_action, ip.
     * @param  array<string, mixed>  $settingBonus  The `bonus` setting group.
     * @param  array<int|string, array<int|string, int>>  $tagGrouped
     *                                                                 Map of torrent_id => [tag_id => 1].
     * @param  \Closure(array<string, mixed>, float, float, float, float, float): void|null  $debugLog
     *                                                                                                  Optional callback invoked for each torrent with the
     *                                                                                                  per-torrent debug values used by the legacy
     *                                                                                                  `calculate_seed_bonus()` diagnostic log line.
     * @return array<string, mixed>
     */
    public function aggregateSeedBonus(
        array $torrentRows,
        array $settingBonus,
        array $tagGrouped,
        int|string|null $officialTag,
        int|string|null $zeroBonusTag,
        mixed $zeroBonusFactor,
        mixed $officialAdditionalFactor,
        ?\Closure $debugLog = null,
    ): array {
        $donortimes_bonus = $settingBonus['donortimes'];
        $perseeding_bonus = $settingBonus['perseeding'];
        $maxseeding_bonus = $settingBonus['maxseeding'];
        $tzero_bonus = $settingBonus['tzero'];
        $nzero_bonus = $settingBonus['nzero'];
        $bzero_bonus = $settingBonus['bzero'];
        $l_bonus = $settingBonus['l'];

        $sqrtof2 = sqrt(2);
        $logofpointone = log(0.1);
        $valueone = $logofpointone / $tzero_bonus;
        $pi = 3.141592653589793;
        $valuetwo = $bzero_bonus * (2 / $pi);
        $valuethree = $logofpointone / ($nzero_bonus - 1);
        $timenow = time();
        $sectoweek = 7 * 24 * 60 * 60;

        $A = $official_a = 0;
        $size = $official_size = '0';
        $count = $torrent_peer_count = $official_torrent_peer_count = 0;
        $last_action = '';
        $ip_arr = [];

        foreach ($torrentRows as $torrent) {
            if ($torrent['last_action'] > $last_action) {
                $last_action = $torrent['last_action'];
            }
            if (! empty($torrent['ip']) && ! isset($ip_arr[$torrent['ip']])) {
                $ip_arr[$torrent['ip']] = $torrent['ip'];
            }
            $torrentSize = (string) ($torrent['size'] ?? 0);
            $size = bcadd($size, is_numeric($torrentSize) ? $torrentSize : '0');
            $weeks_alive = ($timenow - strtotime((string) $torrent['added'])) / $sectoweek;
            $gb_size_raw = (float) $torrent['size'] / 1073741824;
            $gb_size = $gb_size_raw;
            if ($zeroBonusTag && isset($tagGrouped[$torrent['id']][$zeroBonusTag]) && is_numeric($zeroBonusFactor)) {
                $gb_size = $gb_size * (float) $zeroBonusFactor;
            }
            $temp = (1 - exp($valueone * $weeks_alive)) * $gb_size * (1 + $sqrtof2 * exp($valuethree * ((int) $torrent['seeders'] - 1)));
            $A += $temp;
            $count++;
            $torrent_peer_count++;
            $officialAIncrease = 0;
            if ($officialTag && isset($tagGrouped[$torrent['id']][$officialTag])) {
                $officialAIncrease = $temp;
                $official_torrent_peer_count++;
                $official_size = bcadd($official_size, is_numeric($torrentSize) ? $torrentSize : '0');
            }
            $official_a += $officialAIncrease;

            if ($debugLog) {
                $debugLog($torrent, $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease);
            }
        }

        if ($count > $maxseeding_bonus) {
            $count = $maxseeding_bonus;
        }

        $seed_bonus = $seed_points = $valuetwo * atan($A / $l_bonus) + ($perseeding_bonus * $count);
        // Official addition doesn't consider the minimum value.
        $official_bonus = $valuetwo * atan($official_a / $l_bonus);

        $result = compact(
            'seed_points', 'seed_bonus', 'A', 'count', 'torrent_peer_count', 'size', 'last_action',
            'official_bonus', 'official_a', 'official_torrent_peer_count', 'official_size',
        );
        $result['donor_times'] = $donortimes_bonus;
        $result['official_additional_factor'] = $officialAdditionalFactor;
        $result['ip_arr'] = array_keys($ip_arr);

        return $result;
    }
}
