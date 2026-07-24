<?php

declare(strict_types=1);

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Pure seeding-bonus math, drained out of `include/functions.php`
 * (`calculate_seed_bonus()`) as part of Phase 5 of the legacy migration.
 *
 * This class owns ONLY the deterministic computation: given the already
 * fetched torrent rows, the resolved `bonus` settings, tag groupings and
 * the medal additional factor, it reproduces — bit for bit — the formula
 * the legacy function ran inline. All database access, settings lookups
 * and logging stay in the `calculate_seed_bonus()` wrapper so this class
 * is trivially unit-testable.
 *
 * @see \calculate_seed_bonus()
 */
class Bonus
{
    /**
     * Aggregate the per-torrent seeding contributions into the bonus
     * result array consumed by callers (CalculateUserSeedBonus job,
     * TrackerCalculateSeedBonus command, ClaimRepository).
     *
     * @param  array<int, array<string, mixed>>  $torrentRows  Rows with
     *                                                         keys: id, added, size, seeders, last_action, ip.
     * @param  array<string, mixed>  $settingBonus  The `bonus` setting group.
     * @param  array<int|string, array<int|string, int>>  $tagGrouped
     *                                                                 Map of torrent_id => [tag_id => 1].
     * @param  \Closure(array<string, mixed>, float, float, float, float, float): void|null  $debugLog
     *                                                                 Optional callback invoked for each torrent with the
     *                                                                 per-torrent debug values used by the legacy
     *                                                                 `calculate_seed_bonus()` diagnostic log line.
     * @return array<string, mixed>
     */
    public static function aggregateSeedBonus(
        array $torrentRows,
        array $settingBonus,
        array $tagGrouped,
        int|string|null $officialTag,
        int|string|null $zeroBonusTag,
        mixed $zeroBonusFactor,
        float $medalAdditionalFactor,
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

        $A = $official_a = $size = $official_size = 0;
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
            $size = bcadd((string) $size, (string) $torrent['size']);
            $weeks_alive = ($timenow - strtotime($torrent['added'])) / $sectoweek;
            $gb_size_raw = $torrent['size'] / 1073741824;
            $gb_size = $gb_size_raw;
            if ($zeroBonusTag && isset($tagGrouped[$torrent['id']][$zeroBonusTag]) && is_numeric($zeroBonusFactor)) {
                $gb_size = $gb_size * $zeroBonusFactor;
            }
            $temp = (1 - exp($valueone * $weeks_alive)) * $gb_size * (1 + $sqrtof2 * exp($valuethree * ($torrent['seeders'] - 1)));
            $A += $temp;
            $count++;
            $torrent_peer_count++;
            $officialAIncrease = 0;
            if ($officialTag && isset($tagGrouped[$torrent['id']][$officialTag])) {
                $officialAIncrease = $temp;
                $official_torrent_peer_count++;
                $official_size = bcadd((string) $official_size, (string) $torrent['size']);
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
        $medal_bonus = $valuetwo * atan($A / $l_bonus);

        $result = compact(
            'seed_points', 'seed_bonus', 'A', 'count', 'torrent_peer_count', 'size', 'last_action',
            'official_bonus', 'official_a', 'official_torrent_peer_count', 'official_size', 'medal_bonus',
        );
        $result['donor_times'] = $donortimes_bonus;
        $result['official_additional_factor'] = $officialAdditionalFactor;
        $result['medal_additional_factor'] = $medalAdditionalFactor;
        $result['ip_arr'] = array_keys($ip_arr);

        return $result;
    }

    /**
     * Build the per-user bonus breakdown table (basic + optional medal,
     * official and harem addition rows) from an already-resolved
     * calculate_seed_bonus() result and the relevant bonus settings.
     *
     * Drained from include/functions.php (build_bonus_table). All DB /
     * settings access stays in the legacy wrapper; this method is pure
     * apart from i18n (nexus_trans) and size/number formatting helpers.
     *
     * @param  array<string,mixed>  $bonusResult
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public static function buildBonusTable(
        array $bonusResult,
        bool $isDonor,
        $donortimesBonus,
        $officialTag,
        $officialAdditionalFactor,
        $haremFactor,
        $haremAddition,
        array $options = []
    ): array {
        $baseBonusFactor = 1;
        if ($isDonor && $donortimesBonus != 0) {
            $baseBonusFactor = $donortimesBonus;
        }
        $baseBonus = $bonusResult['seed_bonus'] * $baseBonusFactor;
        $totalBonus = $baseBonus;

        $rowSpan = 1;
        $hasHaremAddition = $hasOfficialAddition = $hasMedalAddition = false;
        if ($haremFactor > 0) {
            $rowSpan++;
            $hasHaremAddition = true;
            $totalBonus += $haremAddition * $haremFactor;
        }
        if ($officialAdditionalFactor > 0 && $officialTag) {
            $rowSpan++;
            $hasOfficialAddition = true;
            $totalBonus += $bonusResult['official_bonus'] * $officialAdditionalFactor;
        }
        if ($bonusResult['medal_additional_factor'] > 0) {
            $rowSpan++;
            $hasMedalAddition = true;
            $totalBonus += $bonusResult['medal_bonus'] * $bonusResult['medal_additional_factor'];
        }

        $table = sprintf('<table cellpadding="5" style="%s">', $options['table_style'] ?? '');
        $table .= '<tr>';
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.reward_type'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.count'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.size'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.a_value'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.bonus_base'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.factor'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.got_bonus'));
        $table .= sprintf('<td class="colhead">%s</td>', nexus_trans('bonus.table_thead.total'));
        $table .= '</tr>';

        $table .= sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td rowspan="%s">%s</td></tr>',
            nexus_trans('bonus.reward_types.basic'),
            $bonusResult['torrent_peer_count'],
            Format::size((float) $bonusResult['size']),
            number_format($bonusResult['A'], 3),
            number_format($bonusResult['seed_bonus'], 3),
            $baseBonusFactor,
            number_format($baseBonus, 3),
            $rowSpan,
            number_format($totalBonus, 3)
        );
        if ($hasMedalAddition) {
            $table .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                nexus_trans('bonus.reward_types.medal_addition'),
                $bonusResult['torrent_peer_count'],
                Format::size((float) $bonusResult['size']),
                number_format($bonusResult['A'], 3),
                number_format($bonusResult['medal_bonus'], 3),
                number_format($bonusResult['medal_additional_factor'], 3),
                number_format($bonusResult['medal_bonus'] * $bonusResult['medal_additional_factor'], 3)
            );
        }

        if ($hasOfficialAddition) {
            $table .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                nexus_trans('bonus.reward_types.official_addition'),
                $bonusResult['official_torrent_peer_count'],
                Format::size((float) $bonusResult['official_size']),
                number_format($bonusResult['official_a'], 3),
                number_format($bonusResult['official_bonus'], 3),
                number_format($officialAdditionalFactor, 3),
                number_format($bonusResult['official_bonus'] * $officialAdditionalFactor, 3)
            );
        }

        if ($hasHaremAddition) {
            $table .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                nexus_trans('bonus.reward_types.harem_addition'),
                '--',
                '--',
                '--',
                number_format($haremAddition, 3),
                number_format($haremFactor, 3),
                number_format($haremAddition * $haremFactor, 3)
            );
        }

        $table .= '</table>';

        return [
            'table' => $table,
            'has_harem_addition' => $hasHaremAddition,
            'harem_addition_factor' => $haremFactor,
            'has_official_addition' => $hasOfficialAddition,
            'official_addition_factor' => $officialAdditionalFactor,
            'has_medal_addition' => $hasMedalAddition,
            'medal_addition_factor' => $bonusResult['medal_additional_factor'],
        ];
    }

    /**
     * Add or subtract seed-bonus points from a single user.
     *
     * Mirrors the legacy `KPS()` helper: only executes when the
     * `bonus` tweak is set to `enable` or `disablesave`, and only
     * if the point value is non-zero.
     */
    public static function updatePoints(string $type, float $point, int|string $id, string $bonusTweak): void
    {
        if ($point == 0) {
            return;
        }

        if ($bonusTweak !== 'enable' && $bonusTweak !== 'disablesave') {
            return;
        }

        $op = $type === '-' ? '-' : '+';

        NexusDB::table('users')
            ->where('id', $id)
            ->update([
                'seedbonus' => NexusDB::raw('seedbonus '.$op.' '.$point),
            ]);
    }
}
