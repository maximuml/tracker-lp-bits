<?php

declare(strict_types=1);

namespace App\ViewModels\Bonus;

use App\Support\Format;
use App\Support\Locale;

/**
 * Builds the per-user bonus breakdown table (basic + optional official
 * and harem addition rows) from an already-resolved
 * calculate_seed_bonus() result and the relevant bonus settings.
 *
 * Drained from include/functions.php (build_bonus_table). Pure apart
 * from i18n (Locale) and size/number formatting helpers.
 */
final class BonusTableViewFactory
{
    /**
     * @param  array<string,mixed>  $bonusResult
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public function build(
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
        $hasHaremAddition = $hasOfficialAddition = false;
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

        $headers = [
            Locale::trans('bonus.table_thead.reward_type', [], null),
            Locale::trans('bonus.table_thead.count', [], null),
            Locale::trans('bonus.table_thead.size', [], null),
            Locale::trans('bonus.table_thead.a_value', [], null),
            Locale::trans('bonus.table_thead.bonus_base', [], null),
            Locale::trans('bonus.table_thead.factor', [], null),
            Locale::trans('bonus.table_thead.got_bonus', [], null),
            Locale::trans('bonus.table_thead.total', [], null),
        ];
        $baseRow = [
            Locale::trans('bonus.reward_types.basic', [], null),
            $bonusResult['torrent_peer_count'],
            Format::size((float) $bonusResult['size']),
            number_format($bonusResult['A'], 3),
            number_format($bonusResult['seed_bonus'], 3),
            $baseBonusFactor,
            number_format($baseBonus, 3),
        ];
        $extraRows = [];
        if ($hasOfficialAddition) {
            $extraRows[] = [
                Locale::trans('bonus.reward_types.official_addition', [], null),
                $bonusResult['official_torrent_peer_count'],
                Format::size((float) $bonusResult['official_size']),
                number_format($bonusResult['official_a'], 3),
                number_format($bonusResult['official_bonus'], 3),
                number_format($officialAdditionalFactor, 3),
                number_format($bonusResult['official_bonus'] * $officialAdditionalFactor, 3),
            ];
        }
        if ($hasHaremAddition) {
            $extraRows[] = [
                Locale::trans('bonus.reward_types.harem_addition', [], null),
                '--',
                '--',
                '--',
                number_format($haremAddition, 3),
                number_format($haremFactor, 3),
                number_format($haremAddition * $haremFactor, 3),
            ];
        }

        $table = view('components.bonus.table', [
            'headers' => $headers,
            'baseRow' => $baseRow,
            'extraRows' => $extraRows,
            'rowSpan' => $rowSpan,
            'total' => number_format($totalBonus, 3),
        ])->render();

        return [
            'table' => $table,
            'has_harem_addition' => $hasHaremAddition,
            'harem_addition_factor' => $haremFactor,
            'has_official_addition' => $hasOfficialAddition,
            'official_addition_factor' => $officialAdditionalFactor,
        ];
    }
}
