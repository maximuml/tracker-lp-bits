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
     * @param  array<string, mixed>  $lang
     */
    public function buildShopTable(array $allBonus, array $curUser, array $lang, string $bonus, string $msg, string $lockText): string
    {
        $bonusgiftBonus = (string) $this->globals->get('bonusgift_bonus', 'yes');
        $ratiolimitBonus = (float) $this->globals->get('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) $this->globals->get('dlamountlimit_bonus', 0);
        $SITENAME = (string) $this->globals->get('SITENAME', '');

        ob_start();
        echo "<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n";
        echo '<tr><td class="colhead" colspan="4" align="center"><font class="big">'.$SITENAME.($lang['text_karma_system'] ?? '')."</font></td></tr>\n";
        if ($msg) {
            echo '<tr><td align="center" colspan="4"><font class="striking"><b>'.$msg.'</b></font></td></tr>';
        }
        echo '<tr><td class="text" align="center" colspan="4">'.($lang['text_exchange_your_karma'] ?? '').$bonus.($lang['text_for_goodies'] ?? '');
        echo '<br /><b>'.($lang['text_no_buttons_note'] ?? '').'</b><br /><small style="color: orangered">('.$lockText.')</small></td></tr>';

        echo '<tr><td class="colhead" align="center">'.($lang['col_option'] ?? '').'</td>'.
            '<td class="colhead" align="left">'.($lang['col_description'] ?? '').'</td>'.
            '<td class="colhead" align="center">'.($lang['col_points'] ?? '').'</td>'.
            '<td class="colhead" align="center">'.($lang['col_trade'] ?? '').'</td>'.
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
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.($lang['text_enter_titile'] ?? '').$otheroption_title.($lang['text_click_exchange'] ?? '')."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
            } elseif ($bonusarray['art'] === 'gift_1') {
                $otheroption = '<table width="100%"><tr><td class="embedded"><b>'.($lang['text_username'] ?? '').'</b><input type="text" name="username" style="width: 200px" maxlength="24" /></td><td class="embedded"><b>'.($lang['text_to_be_given'] ?? '')."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' min='100' />".($lang['text_karma_points'] ?? '').'</td></tr><tr><td class="embedded" colspan="2"><b>'.($lang['text_message'] ?? '').'</b><input type="text" name="message" style="width: 400px" maxlength="100" /></td></tr></table>';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.($lang['text_enter_receiver_name'] ?? '')."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".($lang['text_min'] ?? '').'100</td>';
            } elseif ($bonusarray['art'] === 'gift_2') {
                $otheroption = '<table width="100%"><tr><td class="embedded">'.($lang['text_ratio_below'] ?? '').'<select name="ratiocharity"> <option value="0.1"> 0.1</option><option value="0.2"> 0.2</option><option value="0.3" selected="selected"> 0.3</option> <option value="0.4"> 0.4</option> <option value="0.5"> 0.5</option><option value="0.6"> 0.6</option><option value="0.7"> 0.7</option><option value="0.8"> 0.8</option></select>'.($lang['text_and_downloaded_above'] ?? '').' 10 GB</td><td class="embedded"><b>'.($lang['text_to_be_given'] ?? '').'</b><select name="bonuscharity" id="charityselect" > <option value="1000"> 1,000</option><option value="2000"> 2,000</option><option value="3000" selected="selected"> 3000</option> <option value="5000"> 5,000</option> <option value="8000"> 8,000</option><option value="10000"> 10,000</option><option value="20000"> 20,000</option><option value="50000"> 50,000</option></select>'.($lang['text_karma_points'] ?? '').'</td></tr></table>';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.($lang['text_select_receiver_ratio'] ?? '')."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".($lang['text_min'] ?? '').'1,000<br />'.($lang['text_max'] ?? '').'50,000</td>';
            } else {
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
            }

            if (($curUser['seedbonus'] ?? 0) >= $bonusarray['points']) {
                echo $this->renderTradeButton($bonusarray, $curUser, $lang, $ratiolimitBonus, $dlamountlimitBonus);
            } else {
                echo '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['text_more_points_needed'] ?? '').'" disabled="disabled" /></td>';
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
     * @param  array<string, mixed>  $lang
     */
    private function renderTradeButton(array $bonusarray, array $curUser, array $lang, float $ratiolimitBonus, int $dlamountlimitBonus): string
    {
        $art = (string) $bonusarray['art'];
        $sendInvitePermission = PermissionEnum::SEND_INVITE;

        if ($art === 'gift_1') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_karma_gift'] ?? '').'" /></td>';
        }
        if ($art === 'gift_2') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_charity_giving'] ?? '').'" /></td>';
        }
        if ($art === 'invite' || $art === 'tmp_invite') {
            if (! SiteConfig::current()->main->inviteSystem()) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null).'" disabled="disabled" /></td>';
            }
            if (! Permission::can(PermissionEnum::SEND_INVITE)) {
                $requireClass = SiteConfig::current()->authority->permission($sendInvitePermission->value);

                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText($requireClass ?? 0)], null).'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }
        if ($art === 'class') {
            if (UserDisplay::currentClass() >= UC_VIP) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['std_class_above_vip'] ?? '').'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }
        if ($art === 'title') {
            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
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
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['text_ratio_too_high'] ?? '').'" disabled="disabled" /></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }
        if ($art === 'change_username_card') {
            if ($this->bonusCalculationRepository->hasChangeUsernameCard((int) ($curUser['id'] ?? 0))) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['text_change_username_card_already_has'] ?? '').'" disabled="disabled"/></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }
        if ($art === 'rainbow_id') {
            if ($this->bonusCalculationRepository->hasRainbowIdForever((int) ($curUser['id'] ?? 0))) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['text_rainbow_id_already_valid_forever'] ?? '').'" disabled="disabled"/></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }

        return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    public function buildInfoSection(array $curUser, array $lang): string
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
        echo '<tr><td class="colhead" align="center"><font class="big">'.($lang['text_what_is_karma'] ?? '').'</font></td></tr>';
        echo '<tr><td class="text" align="left">';

        echo '<h1>'.($lang['text_get_by_seeding'] ?? '').'</h1>';
        echo '<ul>';
        if ($perseedingBonus > 0) {
            echo '<li>'.$perseedingBonus.($lang['text_point'] ?? '').Strings::addS($perseedingBonus).($lang['text_for_seeding_torrent'] ?? '').$maxseedingBonus.($lang['text_torrent'] ?? '').Strings::addS($maxseedingBonus).')</li>';
        }
        echo '<li>'.($lang['text_bonus_formula_one'] ?? '').$tzeroBonus.($lang['text_bonus_formula_two'] ?? '').$nzeroBonus.($lang['text_bonus_formula_wi'] ?? '').SiteConfig::current()->bonus->zeroBonusFactor().($lang['text_bonus_formula_three'] ?? '').$bzeroBonus.($lang['text_bonus_formula_four'] ?? '').$lBonus.($lang['text_bonus_formula_five'] ?? '').'</li>';
        $minSize = SiteConfig::current()->bonus->minSize();
        if ($minSize > 0) {
            echo '<li>'.sprintf((string) ($lang['text_bonus_mini_size'] ?? ''), Format::size($minSize)).'</li>';
        }
        if ($donortimesBonus) {
            echo '<li>'.($lang['text_donors_always_get'] ?? '').$donortimesBonus.($lang['text_times_of_bonus'] ?? '').'</li>';
        }
        echo '</ul>';

        $seedBonusResult = Bonus::calculateForUser((int) ($curUser['id'] ?? 0), null);
        $A = $seedBonusResult['A'];

        $bonusTableResult = Bonus::buildBonusTableForUser($curUser, $seedBonusResult, ['table_style' => 'width: 50%']);

        $percent = $seedBonusResult['seed_bonus'] * 100 / ($bzeroBonus + $perseedingBonus * $maxseedingBonus);
        echo '<div align="center">'.($lang['text_you_are_currently_getting'] ?? '').round($seedBonusResult['seed_bonus'], 3).($lang['text_point'] ?? '').Strings::addS($seedBonusResult['seed_bonus']).($lang['text_per_hour'] ?? '').' (A = '.round($A, 1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>";

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
            echo '<h1>'.($lang['text_get_by_medal'] ?? '').'</h1>';
            echo '<ul>';
            echo '<li>'.sprintf((string) ($lang['medal_additional_desc'] ?? ''), (int) ($curUser['id'] ?? 0)).'</li>';
            echo '<li>'.($lang['medal_additional_factor'] ?? '').$bonusTableResult['medal_addition_factor'].'</li>';
            echo '</ul>';
        }
        if ($bonusTableResult['has_official_addition']) {
            echo '<h1>'.($lang['text_get_by_seeding_official'] ?? '').'</h1>';
            echo '<ul>';
            echo '<li>'.($lang['official_calculate_method'] ?? '').'</li>';
            echo '<li>'.($lang['official_tag_bonus_additional_factor'] ?? '').$bonusTableResult['official_addition_factor'].'</li>';
            echo '</ul>';
        }

        if ($bonusTableResult['has_harem_addition']) {
            echo '<h1>'.($lang['text_get_by_harem'] ?? '').'</h1>';
            echo '<ul>';
            echo '<li>'.sprintf((string) ($lang['harem_additional_desc'] ?? ''), (int) ($curUser['id'] ?? 0)).'</li>';
            echo '<li>'.($lang['harem_additional_factor'] ?? '').$bonusTableResult['harem_addition_factor'].'</li>';
            echo '<li>'.($lang['harem_additional_note'] ?? '').'</li>';
            echo '</ul>';
        }

        echo '<h1>'.($lang['text_bonus_summary'] ?? '').'</h1>';
        echo '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$bonusTableResult['table'].'</div>';

        echo '<h1>'.($lang['text_other_things_get_bonus'] ?? '').'</h1>';
        echo '<ul>';
        if ($uploadtorrentBonus > 0) {
            echo '<li>'.($lang['text_upload_torrent'] ?? '').$uploadtorrentBonus.($lang['text_point'] ?? '').Strings::addS($uploadtorrentBonus).'</li>';
        }
        if ($starttopicBonus > 0) {
            echo '<li>'.($lang['text_start_topic'] ?? '').$starttopicBonus.($lang['text_point'] ?? '').Strings::addS($starttopicBonus).'</li>';
        }
        if ($makepostBonus > 0) {
            echo '<li>'.($lang['text_make_post'] ?? '').$makepostBonus.($lang['text_point'] ?? '').Strings::addS($makepostBonus).'</li>';
        }
        if ($addcommentBonus > 0) {
            echo '<li>'.($lang['text_add_comment'] ?? '').$addcommentBonus.($lang['text_point'] ?? '').Strings::addS($addcommentBonus).'</li>';
        }
        if ($pollvoteBonus > 0) {
            echo '<li>'.($lang['text_poll_vote'] ?? '').$pollvoteBonus.($lang['text_point'] ?? '').Strings::addS($pollvoteBonus).'</li>';
        }
        if ($offervoteBonus > 0) {
            echo '<li>'.($lang['text_offer_vote'] ?? '').$offervoteBonus.($lang['text_point'] ?? '').Strings::addS($offervoteBonus).'</li>';
        }
        if ($saythanksBonus > 0) {
            echo '<li>'.($lang['text_say_thanks'] ?? '').$saythanksBonus.($lang['text_point'] ?? '').Strings::addS($saythanksBonus).'</li>';
        }
        if ($receivethanksBonus > 0) {
            echo '<li>'.($lang['text_receive_thanks'] ?? '').$receivethanksBonus.($lang['text_point'] ?? '').Strings::addS($receivethanksBonus).'</li>';
        }
        echo $lang['text_howto_get_karma_four'] ?? '';
        if ($ratiolimitBonus > 0) {
            echo '<li>'.($lang['text_user_with_ratio_above'] ?? '').$ratiolimitBonus.($lang['text_and_uploaded_amount_above'] ?? '').$dlamountlimitBonus.($lang['text_cannot_exchange_uploading'] ?? '').'</li>';
        }
        echo ($lang['text_howto_get_karma_five'] ?? '').$uploadtorrentBonus.($lang['text_point'] ?? '').Strings::addS($uploadtorrentBonus).($lang['text_howto_get_karma_six'] ?? '');
        echo '</td></tr></table>';

        return (string) ob_get_clean();
    }
}
