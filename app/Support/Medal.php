<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserMedalStatus;
use Illuminate\Support\Collection;

/**
 * Legacy medal image helper extracted from `include/functions.php`.
 *
 * Backs `build_medal_image()`.
 */
final class Medal
{
    /**
     * Build the HTML for a collection of user medals.
     *
     * Mirrors `build_medal_image()`.
     *
     * @param  Collection<int, \App\Models\Medal>  $medals
     */
    public static function buildImages(Collection $medals, int|string $maxHeight = 200, bool $withActions = false): string
    {
        $permanent = Locale::trans('label.permanent');
        $rows = [];
        foreach ($medals as $medal) {
            $rows[] = [
                'image' => (string) $medal->image_large,
                'name' => (string) $medal->name,
                'expireText' => $medal->pivot->expire_at ? Time::formatDateTime($medal->pivot->expire_at) : $permanent,
                'factor' => $medal->bonus_addition_factor ?? 0,
                'bonusExpireText' => $medal->pivot->bonus_addition_expire_at ? Time::formatDateTime($medal->pivot->bonus_addition_expire_at) : $permanent,
                'pivotId' => $medal->pivot->id,
                'priority' => $medal->pivot->priority ?? 0,
                'wearing' => $medal->pivot->status == UserMedalStatus::WEARING->value,
            ];
        }

        return view('userdetails._medals', [
            'medals' => $rows,
            'withActions' => $withActions,
            'labels' => [
                'expire_at' => Locale::trans('label.expire_at'),
                'bonus_addition_factor' => Locale::trans('medal.fields.bonus_addition_factor'),
                'bonus_addition_expire_at' => Locale::trans('medal.bonus_addition_expire_at'),
                'priority' => Locale::trans('label.priority'),
                'priority_help' => Locale::trans('label.priority_help'),
                'action_wearing' => Locale::trans('medal.action_wearing'),
                'save' => Locale::trans('label.save', [], null),
            ],
        ])->render();
    }
}
