<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\UserDisplay;

/**
 * Builds the thanks-row view model from `getThanksInfo` output — shared
 * by the classic details assembly and the Livewire ThanksSection
 * component.
 */
final class ThanksSectionFactory
{
    /**
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int|string, mixed>  $thanksInfo
     */
    public function build(int $id, array $currentUser, array $thanksInfo): ThanksSection
    {
        $hasThanked = (bool) $thanksInfo['has_thanked'];
        $currentUserHtml = UserDisplay::username((int) ($currentUser['id'] ?? 0), false, true, true, false, false, true);

        $thanksUserIds = [];
        foreach ($thanksInfo['thanks'] as $t) {
            $thanksUserIds[] = (int) ($t->userid ?? 0);
        }
        UserDisplay::preload($thanksUserIds);
        $thanksBy = [];
        foreach ($thanksInfo['thanks'] as $t) {
            if ((int) $t->userid !== (int) $currentUser['id']) {
                $thanksBy[] = UserDisplay::username((int) $t->userid, false, true, true, false, false, true);
            }
        }
        if ($hasThanked) {
            array_unshift($thanksBy, $currentUserHtml);
        }

        $thanksAll = count($thanksInfo['thanks']);
        $andMore = $thanksAll < $thanksInfo['count']
            ? (string) __('details.text_and_more').$thanksInfo['count'].(string) __('details.text_users_in_total')
            : '';

        return new ThanksSection(
            torrentId: $id,
            hasThanked: $hasThanked,
            buttonLabel: (string) __($hasThanked
                ? 'details.submit_you_said_thanks'
                : 'details.submit_say_thanks'),
            addedLabel: (string) __('details.text_thanks_added'),
            thanksBy: $thanksBy,
            noThanks: $thanksAll === 0,
            noThanksLabel: (string) __('details.text_no_thanks_added'),
            andMore: $andMore,
            currentUser: $currentUserHtml,
        );
    }
}
