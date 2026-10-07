<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\UserDisplay;

/**
 * Builds the magic-award row view model from `getMagicInfo` output —
 * shared by the classic details assembly and the Livewire MagicSection
 * component.
 */
final class MagicSectionFactory
{
    private const MAGIC_VISIBLE_GIVERS = 6;

    /**
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int|string, mixed>  $magicInfo
     * @param  array<int|string, mixed>  $bonusOptions
     */
    public function build(int $id, array $currentUser, bool $isOwner, array $magicInfo, array $bonusOptions): MagicSection
    {
        $bonusHas = (float) ($currentUser['seedbonus'] ?? 0);
        $lowBonus = ! $isOwner && (int) $bonusHas < (int) ($bonusOptions[0] ?? 0);

        $options = [];
        if (! $isOwner && ! $lowBonus) {
            foreach ($bonusOptions as $eachTemp) {
                $eachTemp = (int) $eachTemp;
                if ($eachTemp > 0 && $eachTemp <= $bonusHas) {
                    $options[] = $eachTemp;
                }
            }
        }

        $disabledValue = null;
        if ($lowBonus) {
            $disabledValue = (string) __('details.magic_have_no_enough_bonus_value');
        } elseif ((int) $magicInfo['whether_have_give_value'] !== 0) {
            $disabledValue = str_replace(
                'Number',
                (string) $magicInfo['add_value'],
                (string) __('details.magic_value_number')
            );
        }

        $giverIds = [];
        foreach ($magicInfo['givers'] as $giver) {
            $giverIds[] = (int) ($giver->userid ?? 0);
        }
        UserDisplay::preload($giverIds);
        $givers = [];
        foreach ($magicInfo['givers'] as $giver) {
            $givers[] = UserDisplay::username((int) ($giver->userid ?? 0), false, true, true, false, false, true);
        }

        [$haveGotPre, $haveGotPost] = self::splitNumberPlaceholder((string) __('details.magic_haveGotBonus'));
        [$sumGivePre, $sumGivePost] = self::splitNumberPlaceholder((string) __('details.magic_sum_user_give_number'));

        return new MagicSection(
            torrentId: $id,
            options: $options,
            disabledValue: $disabledValue,
            givenLabel: (string) __('details.span_description_have_given'),
            sumValue: (int) $magicInfo['sum_value'],
            countUserNumber: (int) $magicInfo['count_user_number'],
            visibleGivers: array_slice($givers, 0, self::MAGIC_VISIBLE_GIVERS),
            hiddenGivers: array_slice($givers, self::MAGIC_VISIBLE_GIVERS),
            currentUser: UserDisplay::username((int) ($currentUser['id'] ?? 0), false, true, true, false, false, true),
            newestRecordText: (string) __('details.magic_newest_record'),
            sumGivePre: $sumGivePre,
            sumGivePost: $sumGivePost,
            showAllText: (string) __('details.magic_show_all_description'),
            haveGotBonusPre: $haveGotPre,
            haveGotBonusPost: $haveGotPost,
        );
    }

    /**
     * Split a `Number`-placeholder lang string into pre/post parts so
     * the placeholder span can live in Blade.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitNumberPlaceholder(string $value): array
    {
        $pos = strpos($value, 'Number');

        return $pos === false
            ? [$value, '']
            : [substr($value, 0, $pos), substr($value, $pos + 6)];
    }
}
