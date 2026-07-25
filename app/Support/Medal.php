<?php

namespace App\Support;

use Illuminate\Support\Collection;
use App\Models\UserMedal;

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
     */
    public static function buildImages(Collection $medals, int|string $maxHeight = 200, bool $withActions = false): string
    {
        $medalImages = [];
        $wrapBefore = '<form><div style="display: flex;flex-wrap: wrap;justify-content: center;margin-top: 10px;">';
        $wrapAfter = '</div></form>';
        $maxHeight = (int) $maxHeight;

        foreach ($medals as $medal) {
            $html = sprintf(
                '<div style="display: flex;flex-direction: column;justify-content: space-between;margin-right: 10px"><div><img src="%s" title="%s" class="preview" style="max-height: %spx;max-width: %spx"/></div>',
                $medal->image_large,
                $medal->name,
                $maxHeight,
                $maxHeight
            );

            if ($withActions) {
                $html .= sprintf(
                    '<div style="display: flex;flex-direction: column;align-items:flex-start"><span>%s: %s</span><span>%s: %s</span><span>%s: %s</span><label>%s: <input type="number" name="priority_%s" value="%s" style="width: 50px" placeholder="%s"></label>',
                    nexus_trans('label.expire_at'),
                    $medal->pivot->expire_at ? format_datetime($medal->pivot->expire_at) : nexus_trans('label.permanent'),
                    nexus_trans('medal.fields.bonus_addition_factor'),
                    $medal->bonus_addition_factor ?? 0,
                    nexus_trans('medal.bonus_addition_expire_at'),
                    $medal->pivot->bonus_addition_expire_at ? format_datetime($medal->pivot->bonus_addition_expire_at) : nexus_trans('label.permanent'),
                    nexus_trans('label.priority'),
                    $medal->pivot->id,
                    $medal->pivot->priority ?? 0,
                    nexus_trans('label.priority_help')
                );

                $checked = '';
                if ($medal->pivot->status == UserMedal::STATUS_WEARING) {
                    $checked = ' checked';
                }
                $html .= sprintf('<label>%s<input type="checkbox" name="status_%s" value="1"%s></label>', nexus_trans('medal.action_wearing'), $medal->pivot->id, $checked);
                $html .= '</div>';
            }

            $html .= '</div>';
            $medalImages[] = $html;
        }

        if ($withActions) {
            $medalImages[] = sprintf('<div style="display: flex;flex-direction: column;justify-content: space-between;margin-right: 10px"><div></div><div><input type="button" id="save-user-medal-btn" value="%s"/></div></div>', nexus_trans('label.save'));
        }

        return $wrapBefore . implode('', $medalImages) . $wrapAfter;
    }
}
