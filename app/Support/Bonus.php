<?php

declare(strict_types=1);

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Seeding-bonus helpers drained out of `include/functions.php` as part of
 * Phase 5 of the legacy migration.
 *
 * {@see aggregateSeedBonus()} owns the deterministic math: given already
 * fetched torrent rows and resolved settings, it reproduces the legacy
 * formula bit for bit.
 *
 * {@see calculateForUser()} keeps the database / settings lookup half of
 * `calculate_seed_bonus()` so the wrapper in `include/functions.php` stays
 * a one-liner.
 *
 * @see \calculate_seed_bonus()
 */
class Bonus
{
    /**
     * Fetch data and compute a user's per-hour seed bonus.
     *
     * Mirrors the non-deterministic half of `calculate_seed_bonus()`.
     *
     * @param  array<int>|null  $torrentIdArr
     * @return array<string, mixed>
     */
    public static function calculateForUser(int|string $uid, ?array $torrentIdArr = null): array
    {
        $uid = (int) $uid;
        $settingBonus = \App\Models\Setting::get('bonus');
        $minSize = $settingBonus['min_size'] ?? 0;
        $nowStr = date('Y-m-d H:i:s');
        $logPrefix = "[CALCULATE_SEED_BONUS], uid: $uid, torrentIdArr: " . json_encode($torrentIdArr);

        if ($torrentIdArr !== null) {
            if (empty($torrentIdArr)) {
                $torrentIdArr = [-1];
            }
            $torrentQuery = NexusDB::table('torrents')
                ->whereIn('id', $torrentIdArr)
                ->where('size', '>=', $minSize)
                ->select('id', 'added', 'size', 'seeders', NexusDB::raw("'NO_PEER_ID' as peerID"), NexusDB::raw("'' as last_action"), NexusDB::raw("'' as ip"));
        } else {
            $torrentQuery = NexusDB::table('torrents')
                ->leftJoin('peers', 'peers.torrent', '=', 'torrents.id')
                ->where('peers.userid', $uid)
                ->where('peers.seeder', 'yes')
                ->where('torrents.size', '>', $minSize)
                ->groupBy('torrents.id', 'peers.id')
                ->select('torrents.id', 'torrents.added', 'torrents.size', 'torrents.seeders', 'peers.id as peerID', 'peers.last_action', 'peers.ip');
        }
        $sql = $torrentQuery->toSql();

        $tagGrouped = [];
        $torrentResult = $torrentQuery->get()->map(fn ($row) => (array) $row)->all();
        if (! empty($torrentResult)) {
            $torrentIdArrReal = array_column($torrentResult, 'id');
            $tagResult = NexusDB::table('torrent_tags')
                ->whereIn('torrent_id', $torrentIdArrReal)
                ->select('torrent_id', 'tag_id')
                ->get();
            foreach ($tagResult as $tagItem) {
                $tagGrouped[$tagItem->torrent_id][$tagItem->tag_id] = 1;
            }
        }

        $officialTag = \App\Models\Setting::get('bonus.official_tag');
        $officialAdditionalFactor = \App\Models\Setting::get('bonus.official_addition');
        $zeroBonusTag = \App\Models\Setting::get('bonus.zero_bonus_tag');
        $zeroBonusFactor = \App\Models\Setting::get('bonus.zero_bonus_factor');

        $medalQuery = NexusDB::table('medals')
            ->whereIn('id', function ($query) use ($uid, $nowStr) {
                $query->select('medal_id')
                    ->from('user_medals')
                    ->where('uid', $uid)
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('expire_at')->orWhere('expire_at', '>', $nowStr);
                    })
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('bonus_addition_expire_at')->orWhere('bonus_addition_expire_at', '>', $nowStr);
                    });
            });
        if (NexusDB::isMysql()) {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor), 5) as factor');
        } elseif (NexusDB::isPgsql()) {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor)::numeric, 5) as factor');
        } else {
            throw new \RuntimeException('Not supported database');
        }
        $medalAdditionalFactor = floatval($medalQuery->value('factor') ?? 0);

        \do_log("$logPrefix, sql: $sql, count: " . count($torrentResult) . ", officialTag: $officialTag, officialAdditionalFactor: $officialAdditionalFactor, zeroBonusTag: $zeroBonusTag, zeroBonusFactor: $zeroBonusFactor, medalAdditionalFactor: $medalAdditionalFactor");

        $result = self::aggregateSeedBonus(
            $torrentResult,
            $settingBonus,
            $tagGrouped,
            $officialTag,
            $zeroBonusTag,
            $zeroBonusFactor,
            $medalAdditionalFactor,
            $officialAdditionalFactor,
            function ($torrent, $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease) use ($logPrefix) {
                \do_log(sprintf(
                    "$logPrefix, torrent: %s, peer ID: %s, weeks: %s, size_raw: %s GB, size: %s GB, increase A: %s, increase official A: %s",
                    $torrent['id'], $torrent['peerID'] ?? '', $weeks_alive, $gb_size_raw, $gb_size, $temp, $officialAIncrease
                ), 'debug');
            },
        );

        \do_log("$logPrefix, result: " . json_encode($result));

        return $result;
    }

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
        float $donortimesBonus,
        string $officialTag,
        float $officialAdditionalFactor,
        float $haremFactor,
        float $haremAddition,
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
     * Compute the harem seed-bonus addition for a user.
     *
     * Mirrors `calculate_harem_addition()`.
     */
    public static function haremAddition(int|string $uid): float|int|string
    {
        $addition = \Nexus\Database\NexusDB::table('users')
            ->where('invited_by', $uid)
            ->where('status', \App\Models\User::STATUS_CONFIRMED)
            ->where('enabled', \App\Models\User::ENABLED_YES)
            ->sum('seed_points_per_hour');

        \do_log("[HAREM_ADDITION], user: $uid, addition: $addition");

        return $addition;
    }

    /**
     * Build the seed-bonus breakdown table for a user, resolving all
     * the legacy helper dependencies (`calculate_seed_bonus`,
     * `calculate_harem_addition`, `is_donor`, settings) internally.
     *
     * Backs the `build_bonus_table()` helper.
     *
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $bonusResult
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function buildBonusTableForUser(array $user, array $bonusResult = [], array $options = []): array
    {
        if (empty($bonusResult)) {
            $bonusResult = self::calculateForUser((int) ($user['id'] ?? 0));
        }

        $officialTag = (string) \get_setting('bonus.official_tag');
        $officialAdditionalFactor = (float) \get_setting('bonus.official_addition', 0);
        $haremFactor = (float) \get_setting('bonus.harem_addition');
        $haremAddition = self::haremAddition((int) ($user['id'] ?? 0));
        $isDonor = \is_donor($user);
        $donortimesBonus = (float) \get_setting('bonus.donortimes');

        return self::buildBonusTable(
            $bonusResult,
            $isDonor,
            $donortimesBonus,
            $officialTag,
            $officialAdditionalFactor,
            $haremFactor,
            $haremAddition,
            $options,
        );
    }

    /**
     * Add or subtract seed-bonus points from a single user.
     *
     * Mirrors the legacy `KPS()` helper: only executes when the
     * `bonus` tweak is set to `enable` or `disablesave`, and only
     * if the point value is non-zero.
     */
    public static function updatePoints(string $type, float $point, int|string $id, ?string $bonusTweak = null): void
    {
        if ($bonusTweak === null) {
            $bonusTweak = (string) ($GLOBALS['bonus_tweak'] ?? '');
        }

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
