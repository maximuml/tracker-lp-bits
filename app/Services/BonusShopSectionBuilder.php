<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\BonusCalculationRepository;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Locale;
use App\Support\Strings;
use App\Support\UserDisplay;

/**
 * Emits the bonus-shop exchange table and the "what is karma" info
 * section HTML. Extracted from BonusPageService to keep both classes
 * under the 400-line ratchet.
 */
final class BonusShopSectionBuilder
{
    public function __construct(
        private readonly BonusCalculationRepository $bonusCalculationRepository,
        private readonly Globals $globals,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     */
    public function buildShopTable(array $allBonus, array $curUser, string $bonus, string $msg, string $lockText): string
    {
        $bonusgiftBonus = (string) $this->globals->get('bonusgift_bonus', 'yes');
        $ratiolimitBonus = (float) $this->globals->get('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) $this->globals->get('dlamountlimit_bonus', 0);
        $SITENAME = (string) $this->globals->get('SITENAME', '');

        ob_start();
        echo "<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n";
        echo '<tr><td class="colhead" colspan="4" align="center"><font class="big">'.$SITENAME.(__('legacy/mybonus.text_karma_system'))."</font></td></tr>\n";
        if ($msg) {
            echo '<tr><td align="center" colspan="4"><font class="striking"><b>'.$msg.'</b></font></td></tr>';
        }
        echo '<tr><td class="text" align="center" colspan="4">'.(__('legacy/mybonus.text_exchange_your_karma')).$bonus.(__('legacy/mybonus.text_for_goodies'));
        echo '<br /><b>'.(__('legacy/mybonus.text_no_buttons_note')).'</b><br /><small style="color: orangered">('.$lockText.')</small></td></tr>';

        echo '<tr><td class="colhead" align="center">'.(__('legacy/mybonus.col_option')).'</td>'.
            '<td class="colhead" align="left">'.(__('legacy/mybonus.col_description')).'</td>'.
            '<td class="colhead" align="center">'.(__('legacy/mybonus.col_points')).'</td>'.
            '<td class="colhead" align="center">'.(__('legacy/mybonus.col_trade')).'</td>'.
            '</tr>';

        for ($i = 0; $i < count($allBonus); $i++) {
            $bonusarray = $allBonus[$i];
            if (
                ($bonusarray['art'] === 'gift_1' && $bonusgiftBonus === 'no')
                || ($bonusarray['art'] === 'cancel_hr' && ! HitAndRun::getIsEnabled())
            ) {
                continue;
            }

            echo '<tr>';
            echo '<form action="?action=exchange" method="post">';
            echo '<td class="rowhead_center"><input type="hidden" name="option" value="'.$i.'" /><b>'.($i + 1).'</b></td>';

            if ($bonusarray['art'] === 'title') {
                $otheroption_title = '<input type="text" name="title" style="width: 200px" maxlength="30" />';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.(__('legacy/mybonus.text_enter_titile')).$otheroption_title.(__('legacy/mybonus.text_click_exchange'))."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
            } elseif ($bonusarray['art'] === 'gift_1') {
                $otheroption = '<table width="100%"><tr><td class="embedded"><b>'.(__('legacy/mybonus.text_username')).'</b><input type="text" name="username" style="width: 200px" maxlength="24" /></td><td class="embedded"><b>'.(__('legacy/mybonus.text_to_be_given'))."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' min='100' />".(__('legacy/mybonus.text_karma_points')).'</td></tr><tr><td class="embedded" colspan="2"><b>'.(__('legacy/mybonus.text_message')).'</b><input type="text" name="message" style="width: 400px" maxlength="100" /></td></tr></table>';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.(__('legacy/mybonus.text_enter_receiver_name'))."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".(__('legacy/mybonus.text_min')).'100</td>';
            } elseif ($bonusarray['art'] === 'gift_2') {
                $otheroption = '<table width="100%"><tr><td class="embedded">'.(__('legacy/mybonus.text_ratio_below')).'<select name="ratiocharity"> <option value="0.1"> 0.1</option><option value="0.2"> 0.2</option><option value="0.3" selected="selected"> 0.3</option> <option value="0.4"> 0.4</option> <option value="0.5"> 0.5</option><option value="0.6"> 0.6</option><option value="0.7"> 0.7</option><option value="0.8"> 0.8</option></select>'.(__('legacy/mybonus.text_and_downloaded_above')).' 10 GB</td><td class="embedded"><b>'.(__('legacy/mybonus.text_to_be_given')).'</b><select name="bonuscharity" id="charityselect" > <option value="1000"> 1,000</option><option value="2000"> 2,000</option><option value="3000" selected="selected"> 3000</option> <option value="5000"> 5,000</option> <option value="8000"> 8,000</option><option value="10000"> 10,000</option><option value="20000"> 20,000</option><option value="50000"> 50,000</option></select>'.(__('legacy/mybonus.text_karma_points')).'</td></tr></table>';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.(__('legacy/mybonus.text_select_receiver_ratio'))."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".(__('legacy/mybonus.text_min')).'1,000<br />'.(__('legacy/mybonus.text_max')).'50,000</td>';
            } else {
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
            }

            if (($curUser['seedbonus'] ?? 0) >= $bonusarray['points']) {
                echo $this->renderTradeButton($bonusarray, $curUser, $ratiolimitBonus, $dlamountlimitBonus);
            } else {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.text_more_points_needed')).'" disabled="disabled" /></td>';
            }
            echo '</form>';
            echo '</tr>';
        }

        echo '</table><br />';

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     * @param  array<string, mixed>  $curUser
     */
    private function renderTradeButton(array $bonusarray, array $curUser, float $ratiolimitBonus, int $dlamountlimitBonus): string
    {
        $art = (string) $bonusarray['art'];
        $sendInvitePermission = PermissionEnum::SEND_INVITE;

        if ($art === 'gift_1') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_karma_gift')).'" /></td>';
        }
        if ($art === 'gift_2') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_charity_giving')).'" /></td>';
        }
        if ($art === 'invite' || $art === 'tmp_invite') {
            if (! SiteConfig::current()->main->inviteSystem()) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null).'" disabled="disabled" /></td>';
            }
            if (! Permission::can(PermissionEnum::SEND_INVITE)) {
                $requireClass = SiteConfig::current()->authority->permission($sendInvitePermission->value);

                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText($requireClass ?? 0)], null).'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }
        if ($art === 'class') {
            if (UserDisplay::currentClass() >= UC_VIP) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.std_class_above_vip')).'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }
        if ($art === 'title') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }
        if ($art === 'traffic') {
            if (($curUser['downloaded'] ?? 0) > 0) {
                if (($curUser['uploaded'] ?? 0) > $dlamountlimitBonus * 1073741824) {
                    $ratio = ($curUser['uploaded'] ?? 0) / ($curUser['downloaded'] ?? 1);
                } else {
                    $ratio = 0;
                }
            } else {
                $ratio = $ratiolimitBonus + 1;
            }
            if ($ratiolimitBonus > 0 && $ratio > $ratiolimitBonus) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.text_ratio_too_high')).'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }
        if ($art === 'change_username_card') {
            if ($this->bonusCalculationRepository->hasChangeUsernameCard((int) ($curUser['id'] ?? 0))) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.text_change_username_card_already_has')).'" disabled="disabled"/></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }
        if ($art === 'rainbow_id') {
            if ($this->bonusCalculationRepository->hasRainbowIdForever((int) ($curUser['id'] ?? 0))) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.text_rainbow_id_already_valid_forever')).'" disabled="disabled"/></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
        }

        return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.(__('legacy/mybonus.submit_exchange')).'" /></td>';
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function buildInfoSection(array $curUser): string
    {
        $perseedingBonus = (float) $this->globals->get('perseeding_bonus', 0);
        $maxseedingBonus = (int) $this->globals->get('maxseeding_bonus', 0);
        $tzeroBonus = (float) $this->globals->get('tzero_bonus', 0);
        $nzeroBonus = (float) $this->globals->get('nzero_bonus', 0);
        $bzeroBonus = (float) $this->globals->get('bzero_bonus', 0);
        $lBonus = (float) $this->globals->get('l_bonus', 0);
        $donortimesBonus = (float) $this->globals->get('donortimes_bonus', 0);
        $uploadtorrentBonus = (float) $this->globals->get('uploadtorrent_bonus', 0);
        $starttopicBonus = (float) $this->globals->get('starttopic_bonus', 0);
        $makepostBonus = (float) $this->globals->get('makepost_bonus', 0);
        $addcommentBonus = (float) $this->globals->get('addcomment_bonus', 0);
        $pollvoteBonus = (float) $this->globals->get('pollvote_bonus', 0);
        $offervoteBonus = (float) $this->globals->get('offervote_bonus', 0);
        $saythanksBonus = (float) $this->globals->get('saythanks_bonus', 0);
        $receivethanksBonus = (float) $this->globals->get('receivethanks_bonus', 0);
        $ratiolimitBonus = (float) $this->globals->get('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) $this->globals->get('dlamountlimit_bonus', 0);

        ob_start();
        echo '<table width="97%" cellpadding="3">';
        echo '<tr><td class="colhead" align="center"><font class="big">'.(__('legacy/mybonus.text_what_is_karma')).'</font></td></tr>';
        echo '<tr><td class="text" align="left">';

        echo '<h1>'.(__('legacy/mybonus.text_get_by_seeding')).'</h1>';
        echo '<ul>';
        if ($perseedingBonus > 0) {
            echo '<li>'.$perseedingBonus.(__('legacy/mybonus.text_point')).Strings::addS($perseedingBonus).(__('legacy/mybonus.text_for_seeding_torrent')).$maxseedingBonus.(__('legacy/mybonus.text_torrent')).Strings::addS($maxseedingBonus).')</li>';
        }
        echo '<li>'.(__('legacy/mybonus.text_bonus_formula_one')).$tzeroBonus.(__('legacy/mybonus.text_bonus_formula_two')).$nzeroBonus.(__('legacy/mybonus.text_bonus_formula_wi')).SiteConfig::current()->bonus->zeroBonusFactor().(__('legacy/mybonus.text_bonus_formula_three')).$bzeroBonus.(__('legacy/mybonus.text_bonus_formula_four')).$lBonus.(__('legacy/mybonus.text_bonus_formula_five')).'</li>';
        $minSize = SiteConfig::current()->bonus->minSize();
        if ($minSize > 0) {
            echo '<li>'.sprintf((string) (__('legacy/mybonus.text_bonus_mini_size')), Format::size($minSize)).'</li>';
        }
        if ($donortimesBonus) {
            echo '<li>'.(__('legacy/mybonus.text_donors_always_get')).$donortimesBonus.(__('legacy/mybonus.text_times_of_bonus')).'</li>';
        }
        echo '</ul>';

        $seedBonusResult = Bonus::calculateForUser((int) ($curUser['id'] ?? 0), null);
        $A = $seedBonusResult['A'];

        $bonusTableResult = Bonus::buildBonusTableForUser($curUser, $seedBonusResult, ['table_style' => 'width: 50%']);

        $percent = $seedBonusResult['seed_bonus'] * 100 / ($bzeroBonus + $perseedingBonus * $maxseedingBonus);
        echo '<div align="center">'.(__('legacy/mybonus.text_you_are_currently_getting')).round($seedBonusResult['seed_bonus'], 3).(__('legacy/mybonus.text_point')).Strings::addS($seedBonusResult['seed_bonus']).(__('legacy/mybonus.text_per_hour')).' (A = '.round($A, 1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>";

        if ($percent <= 30) {
            $loadpic = 'loadbarred';
        } elseif ($percent <= 60) {
            $loadpic = 'loadbaryellow';
        } else {
            $loadpic = 'loadbargreen';
        }
        $width = $percent * 4;
        echo '<img class="'.$loadpic.'" src="pic/trans.gif" style="width: '.$width.'px;" alt="'.$percent.'%" /></td></tr></table>';

        if ($bonusTableResult['has_medal_addition']) {
            echo '<h1>'.(__('legacy/mybonus.text_get_by_medal')).'</h1>';
            echo '<ul>';
            echo '<li>'.sprintf((string) (__('legacy/mybonus.medal_additional_desc')), (int) ($curUser['id'] ?? 0)).'</li>';
            echo '<li>'.(__('legacy/mybonus.medal_additional_factor')).$bonusTableResult['medal_addition_factor'].'</li>';
            echo '</ul>';
        }
        if ($bonusTableResult['has_official_addition']) {
            echo '<h1>'.(__('legacy/mybonus.text_get_by_seeding_official')).'</h1>';
            echo '<ul>';
            echo '<li>'.(__('legacy/mybonus.official_calculate_method')).'</li>';
            echo '<li>'.(__('legacy/mybonus.official_tag_bonus_additional_factor')).$bonusTableResult['official_addition_factor'].'</li>';
            echo '</ul>';
        }

        if ($bonusTableResult['has_harem_addition']) {
            echo '<h1>'.(__('legacy/mybonus.text_get_by_harem')).'</h1>';
            echo '<ul>';
            echo '<li>'.sprintf((string) (__('legacy/mybonus.harem_additional_desc')), (int) ($curUser['id'] ?? 0)).'</li>';
            echo '<li>'.(__('legacy/mybonus.harem_additional_factor')).$bonusTableResult['harem_addition_factor'].'</li>';
            echo '<li>'.(__('legacy/mybonus.harem_additional_note')).'</li>';
            echo '</ul>';
        }

        echo '<h1>'.(__('legacy/mybonus.text_bonus_summary')).'</h1>';
        echo '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$bonusTableResult['table'].'</div>';

        echo '<h1>'.(__('legacy/mybonus.text_other_things_get_bonus')).'</h1>';
        echo '<ul>';
        if ($uploadtorrentBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_upload_torrent')).$uploadtorrentBonus.(__('legacy/mybonus.text_point')).Strings::addS($uploadtorrentBonus).'</li>';
        }
        if ($starttopicBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_start_topic')).$starttopicBonus.(__('legacy/mybonus.text_point')).Strings::addS($starttopicBonus).'</li>';
        }
        if ($makepostBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_make_post')).$makepostBonus.(__('legacy/mybonus.text_point')).Strings::addS($makepostBonus).'</li>';
        }
        if ($addcommentBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_add_comment')).$addcommentBonus.(__('legacy/mybonus.text_point')).Strings::addS($addcommentBonus).'</li>';
        }
        if ($pollvoteBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_poll_vote')).$pollvoteBonus.(__('legacy/mybonus.text_point')).Strings::addS($pollvoteBonus).'</li>';
        }
        if ($offervoteBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_offer_vote')).$offervoteBonus.(__('legacy/mybonus.text_point')).Strings::addS($offervoteBonus).'</li>';
        }
        if ($saythanksBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_say_thanks')).$saythanksBonus.(__('legacy/mybonus.text_point')).Strings::addS($saythanksBonus).'</li>';
        }
        if ($receivethanksBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_receive_thanks')).$receivethanksBonus.(__('legacy/mybonus.text_point')).Strings::addS($receivethanksBonus).'</li>';
        }
        echo __('legacy/mybonus.text_howto_get_karma_four');
        if ($ratiolimitBonus > 0) {
            echo '<li>'.(__('legacy/mybonus.text_user_with_ratio_above')).$ratiolimitBonus.(__('legacy/mybonus.text_and_uploaded_amount_above')).$dlamountlimitBonus.(__('legacy/mybonus.text_cannot_exchange_uploading')).'</li>';
        }
        echo __('legacy/mybonus.text_howto_get_karma_five').$uploadtorrentBonus.(__('legacy/mybonus.text_point')).Strings::addS($uploadtorrentBonus).(__('legacy/mybonus.text_howto_get_karma_six'));
        echo '</td></tr></table>';

        return (string) ob_get_clean();
    }
}
