<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Strings;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

/**
 * Prepares section data for the mybonus (karma) page, replacing the
 * legacy my_bonus_content.php partial with typed Blade-rendered sections.
 *
 * Sections:
 *  - shop:   bonus exchange table with all options
 *  - info:   "what is karma" explanation with seeding formula
 */
final class BonusPageService
{
    private BonusRepository $bonusRep;

    public function __construct(BonusRepository $bonusRep)
    {
        $this->bonusRep = $bonusRep;
    }

    /**
     * Build the data for the requested action.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (app(CurrentUser::class)->get() ?? []);
        $lang = (array) (app(Globals::class)->get('lang_mybonus') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $bonusTweak = (string) app(Globals::class)->get('bonus_tweak', '');
        if ($bonusTweak === 'disable' || $bonusTweak === 'disablesave') {
            LegacyResponse::abort(
                (string) ($lang['std_sorry'] ?? ''),
                (string) ($lang['std_karma_system_disabled'] ?? '').($bonusTweak === 'disablesave' ? '<b>'.($lang['std_points_active'] ?? '').'</b>' : ''),
                false
            );
        }

        $lockSeconds = 10;
        $lockText = sprintf((string) ($lang['lock_text'] ?? ''), $lockSeconds);

        $allBonus = $this->buildBonusArray($lang);

        $action = htmlspecialchars((string) $request->query('action', ''));
        $do = htmlspecialchars((string) $request->query('do', ''));

        $msg = $this->resolveDoMessage($do, $lang, $curUser, $lockText);

        $bonus = number_format((float) ($curUser['seedbonus'] ?? 0), 1);

        $shopHtml = '';
        $infoHtml = '';
        if (! $action) {
            $shopHtml = $this->buildShopTable($allBonus, $curUser, $lang, $bonus, $msg, $lockText);
            $infoHtml = $this->buildInfoSection($curUser, $lang);
        }

        return [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'do' => $do,
            'msg' => $msg,
            'bonus' => $bonus,
            'lockText' => $lockText,
            'allBonus' => $allBonus,
            'shopHtml' => $shopHtml,
            'infoHtml' => $infoHtml,
            'sitename' => (string) app(Globals::class)->get('SITENAME', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<int, array<string, mixed>>
     */
    public function buildBonusArray(array $lang): array
    {
        $onegbuploadBonus = (float) app(Globals::class)->get('onegbupload_bonus', 0);
        $fivegbuploadBonus = (float) app(Globals::class)->get('fivegbupload_bonus', 0);
        $tengbuploadBonus = (float) app(Globals::class)->get('tengbupload_bonus', 0);
        $oneinviteBonus = (float) app(Globals::class)->get('oneinvite_bonus', 0);
        $customtitleBonus = (float) app(Globals::class)->get('customtitle_bonus', 0);
        $vipstatusBonus = (float) app(Globals::class)->get('vipstatus_bonus', 0);
        $basictaxBonus = (float) app(Globals::class)->get('basictax_bonus', 0);
        $taxpercentageBonus = (float) app(Globals::class)->get('taxpercentage_bonus', 0);

        $results = [];

        // 1.0 GB Uploaded
        $results[] = $this->bonusItem($onegbuploadBonus, 'traffic', 1073741824, (string) ($lang['text_uploaded_one'] ?? ''), (string) ($lang['text_uploaded_note'] ?? ''));
        // 5.0 GB Uploaded
        $results[] = $this->bonusItem($fivegbuploadBonus, 'traffic', 5368709120, (string) ($lang['text_uploaded_two'] ?? ''), (string) ($lang['text_uploaded_note'] ?? ''));
        // 10.0 GB Uploaded
        $results[] = $this->bonusItem($tengbuploadBonus, 'traffic', 10737418240, (string) ($lang['text_uploaded_three'] ?? ''), (string) ($lang['text_uploaded_note'] ?? ''));
        // 100.0 GB Uploaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->hundredGbUpload(), 'traffic', 107374182400, (string) ($lang['text_uploaded_four'] ?? ''), (string) ($lang['text_uploaded_note'] ?? ''));
        // 10.0 GB Downloaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->tenGbDownload(), 'traffic_downloaded', 10737418240, (string) ($lang['text_downloaded_ten_gb'] ?? ''), (string) ($lang['text_download_note'] ?? ''));
        // 100.0 GB Downloaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->hundredGbDownload(), 'traffic_downloaded', 107374182400, (string) ($lang['text_downloaded_hundred_gb'] ?? ''), (string) ($lang['text_download_note'] ?? ''));

        // Invite
        if ($oneinviteBonus > 0) {
            $results[] = $this->bonusItem($oneinviteBonus, 'invite', 1, (string) ($lang['text_buy_invite'] ?? ''), (string) ($lang['text_buy_invite_note'] ?? ''));
        }

        // Tmp Invite
        $tmpInviteBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($tmpInviteBonus > 0) {
            $results[] = $this->bonusItem($tmpInviteBonus, 'tmp_invite', 1, (string) ($lang['text_buy_tmp_invite'] ?? ''), (string) ($lang['text_buy_tmp_invite_note'] ?? ''));
        }

        // Custom Title
        $results[] = $this->bonusItem($customtitleBonus, 'title', 0, (string) ($lang['text_custom_title'] ?? ''), (string) ($lang['text_custom_title_note'] ?? ''));

        // VIP Status
        $results[] = $this->bonusItem($vipstatusBonus, 'class', 0, (string) ($lang['text_vip_status'] ?? ''), (string) ($lang['text_vip_status_note'] ?? ''));

        // Bonus Gift
        $giftDesc = (string) ($lang['text_bonus_gift_note'] ?? '');
        if ($basictaxBonus || $taxpercentageBonus) {
            $onehundredaftertax = 100 - $taxpercentageBonus - $basictaxBonus;
            $giftDesc .= '<br /><br />'.($lang['text_system_charges_receiver'] ?? '').'<b>'.($basictaxBonus ? $basictaxBonus.($lang['text_tax_bonus_point'] ?? '').Strings::addS($basictaxBonus).($taxpercentageBonus ? ($lang['text_tax_plus'] ?? '') : '') : '').($taxpercentageBonus ? $taxpercentageBonus.($lang['text_percent_of_transfered_amount'] ?? '') : '').'</b>'.($lang['text_as_tax'] ?? '').$onehundredaftertax.($lang['text_tax_example_note'] ?? '');
        }
        $results[] = $this->bonusItem(100, 'gift_1', 0, (string) ($lang['text_bonus_gift'] ?? ''), $giftDesc);

        // Attendance card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyAttendanceCard(), 'attendance_card', 0, (string) ($lang['text_attendance_card'] ?? ''), (string) ($lang['text_attendance_card_note'] ?? ''));

        // Rainbow ID
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyRainbowId(), 'rainbow_id', 0, (string) ($lang['text_buy_rainbow_id'] ?? ''), (string) ($lang['text_buy_rainbow_id_note'] ?? ''));

        // Change username card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyChangeUsernameCard(), 'change_username_card', 0, (string) ($lang['text_buy_change_username_card'] ?? ''), (string) ($lang['text_buy_change_username_card_note'] ?? ''));

        // Donate
        $results[] = $this->bonusItem(1000, 'gift_2', 0, (string) ($lang['text_charity_giving'] ?? ''), (string) ($lang['text_charity_giving_note'] ?? ''));

        // Cancel hit and run
        $cancelHrDesc = '<p>
            <span style="">'.($lang['text_cancel_hr_label'] ?? '').'</span>
            <input type="number" name="hr_id" />
        </p>';
        $results[] = $this->bonusItem(BonusLogs::getBonusForCancelHitAndRun(), 'cancel_hr', 0, (string) ($lang['text_cancel_hr_title'] ?? ''), $cancelHrDesc);

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function bonusItem(float $points, string $art, int $menge, string $name, string $description): array
    {
        return [
            'points' => $points,
            'art' => $art,
            'menge' => $menge,
            'name' => $name,
            'description' => $description,
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     */
    private function resolveDoMessage(string $do, array $lang, array $curUser, string $lockText): string
    {
        return match ($do) {
            'upload' => (string) ($lang['text_success_upload'] ?? ''),
            'download' => (string) ($lang['text_success_download'] ?? ''),
            'invite' => (string) ($lang['text_success_invites'] ?? ''),
            'tmp_invite' => (string) ($lang['text_success_tmp_invites'] ?? ''),
            'vip' => (string) ($lang['text_success_vip'] ?? '').'<b>'.UserClass::name(UC_VIP, false, false, true).'</b>'.($lang['text_success_vip_two'] ?? ''),
            'vipfalse' => (string) ($lang['text_no_permission'] ?? ''),
            'title' => sprintf((string) ($lang['text_success_custom_title'] ?? ''), (string) ($curUser['title'] ?? '')),
            'transfer' => (string) ($lang['text_success_gift'] ?? ''),
            'charity' => (string) ($lang['text_success_charity'] ?? ''),
            'cancel_hr' => (string) ($lang['text_success_cancel_hr'] ?? ''),
            'buy_medal' => (string) ($lang['text_success_buy_medal'] ?? ''),
            'attendance_card' => (string) ($lang['text_success_buy_attendance_card'] ?? ''),
            'rainbow_id' => (string) ($lang['text_success_buy_rainbow_id'] ?? ''),
            'change_username_card' => (string) ($lang['text_success_buy_change_username_card'] ?? ''),
            'duplicated' => $lockText,
            default => '',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function buildShopTable(array $allBonus, array $curUser, array $lang, string $bonus, string $msg, string $lockText): string
    {
        $bonusgiftBonus = (string) app(Globals::class)->get('bonusgift_bonus', 'yes');
        $ratiolimitBonus = (float) app(Globals::class)->get('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) app(Globals::class)->get('dlamountlimit_bonus', 0);
        $SITENAME = (string) app(Globals::class)->get('SITENAME', '');

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
                $otheroption = '<table width="100%"><tr><td class="embedded">'.($lang['text_ratio_below'] ?? '').'<select name="ratiocharity"> <option value="0.1"> 0.1</option><option value="0.2"> 0.2</option><option value="0.3" selected="selected"> 0.3</option> <option value="0.4"> 0.4</option> <option value="0.5"> 0.5</option> <option value="0.6"> 0.6</option><option value="0.7"> 0.7</option><option value="0.8"> 0.8</option></select>'.($lang['text_and_downloaded_above'] ?? '').' 10 GB</td><td class="embedded"><b>'.($lang['text_to_be_given'] ?? '').'</b><select name="bonuscharity" id="charityselect" > <option value="1000"> 1,000</option><option value="2000"> 2,000</option><option value="3000" selected="selected"> 3000</option> <option value="5000"> 5,000</option> <option value="8000"> 8,000</option> <option value="10000"> 10,000</option><option value="20000"> 20,000</option><option value="50000"> 50,000</option></select>'.($lang['text_karma_points'] ?? '').'</td></tr></table>';
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
            if ($this->bonusRep->hasChangeUsernameCard((int) ($curUser['id'] ?? 0))) {
                return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['text_change_username_card_already_has'] ?? '').'" disabled="disabled"/></td>';
            }

            return '<td class="rowfollow" align="center"><input type="submit" name="submit" value="'.($lang['submit_exchange'] ?? '').'" /></td>';
        }
        if ($art === 'rainbow_id') {
            if ($this->bonusRep->hasRainbowIdForever((int) ($curUser['id'] ?? 0))) {
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
    private function buildInfoSection(array $curUser, array $lang): string
    {
        $perseedingBonus = (float) app(Globals::class)->get('perseeding_bonus', 0);
        $maxseedingBonus = (int) app(Globals::class)->get('maxseeding_bonus', 0);
        $tzeroBonus = (float) app(Globals::class)->get('tzero_bonus', 0);
        $nzeroBonus = (float) app(Globals::class)->get('nzero_bonus', 0);
        $bzeroBonus = (float) app(Globals::class)->get('bzero_bonus', 0);
        $lBonus = (float) app(Globals::class)->get('l_bonus', 0);
        $donortimesBonus = (float) app(Globals::class)->get('donortimes_bonus', 0);
        $uploadtorrentBonus = (float) app(Globals::class)->get('uploadtorrent_bonus', 0);
        $starttopicBonus = (float) app(Globals::class)->get('starttopic_bonus', 0);
        $makepostBonus = (float) app(Globals::class)->get('makepost_bonus', 0);
        $addcommentBonus = (float) app(Globals::class)->get('addcomment_bonus', 0);
        $pollvoteBonus = (float) app(Globals::class)->get('pollvote_bonus', 0);
        $offervoteBonus = (float) app(Globals::class)->get('offervote_bonus', 0);
        $saythanksBonus = (float) app(Globals::class)->get('saythanks_bonus', 0);
        $receivethanksBonus = (float) app(Globals::class)->get('receivethanks_bonus', 0);
        $ratiolimitBonus = (float) app(Globals::class)->get('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) app(Globals::class)->get('dlamountlimit_bonus', 0);

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
