<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\BonusCalculationRepository;
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
use App\ViewModels\BonusPageViewModel;
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
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly BonusCalculationRepository $bonusCalculationRepository,
    ) {}

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): BonusPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $bonusTweak = (string) $this->globals->get('bonus_tweak', '');
        if ($bonusTweak === 'disable' || $bonusTweak === 'disablesave') {
            LegacyResponse::abort(
                (string) (__('legacy/mybonus.std_sorry')),
                (string) (__('legacy/mybonus.std_karma_system_disabled')).($bonusTweak === 'disablesave' ? '<b>'.(__('legacy/mybonus.std_points_active')).'</b>' : ''),
                false
            );
        }

        $lockSeconds = 10;
        $lockText = sprintf((string) (__('legacy/mybonus.lock_text')), $lockSeconds);

        $allBonus = $this->buildBonusArray();

        $action = htmlspecialchars((string) $request->query('action', ''));
        $do = htmlspecialchars((string) $request->query('do', ''));

        $msg = $this->resolveDoMessage($do, $curUser, $lockText);

        $bonus = number_format((float) ($curUser['seedbonus'] ?? 0), 1);

        $shopHtml = '';
        $infoHtml = '';
        if (! $action) {
            $shopHtml = $this->buildShopTable($allBonus, $curUser, $bonus, $msg, $lockText);
            $infoHtml = $this->buildInfoSection($curUser);
        }

        return new BonusPageViewModel(
            curUser: $curUser,
            userId: $userId,
            action: $action,
            do: $do,
            msg: $msg,
            bonus: $bonus,
            lockText: $lockText,
            allBonus: $allBonus,
            shopHtml: $shopHtml,
            infoHtml: $infoHtml,
            sitename: (string) $this->globals->get('SITENAME', ''),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildBonusArray(): array
    {
        $onegbuploadBonus = (float) $this->globals->get('onegbupload_bonus', 0);
        $fivegbuploadBonus = (float) $this->globals->get('fivegbupload_bonus', 0);
        $tengbuploadBonus = (float) $this->globals->get('tengbupload_bonus', 0);
        $oneinviteBonus = (float) $this->globals->get('oneinvite_bonus', 0);
        $customtitleBonus = (float) $this->globals->get('customtitle_bonus', 0);
        $vipstatusBonus = (float) $this->globals->get('vipstatus_bonus', 0);
        $basictaxBonus = (float) $this->globals->get('basictax_bonus', 0);
        $taxpercentageBonus = (float) $this->globals->get('taxpercentage_bonus', 0);

        $results = [];

        // 1.0 GB Uploaded
        $results[] = $this->bonusItem($onegbuploadBonus, 'traffic', 1073741824, (string) (__('legacy/mybonus.text_uploaded_one')), (string) (__('legacy/mybonus.text_uploaded_note')));
        // 5.0 GB Uploaded
        $results[] = $this->bonusItem($fivegbuploadBonus, 'traffic', 5368709120, (string) (__('legacy/mybonus.text_uploaded_two')), (string) (__('legacy/mybonus.text_uploaded_note')));
        // 10.0 GB Uploaded
        $results[] = $this->bonusItem($tengbuploadBonus, 'traffic', 10737418240, (string) (__('legacy/mybonus.text_uploaded_three')), (string) (__('legacy/mybonus.text_uploaded_note')));
        // 100.0 GB Uploaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->hundredGbUpload(), 'traffic', 107374182400, (string) (__('legacy/mybonus.text_uploaded_four')), (string) (__('legacy/mybonus.text_uploaded_note')));
        // 10.0 GB Downloaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->tenGbDownload(), 'traffic_downloaded', 10737418240, (string) (__('legacy/mybonus.text_downloaded_ten_gb')), (string) (__('legacy/mybonus.text_download_note')));
        // 100.0 GB Downloaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->hundredGbDownload(), 'traffic_downloaded', 107374182400, (string) (__('legacy/mybonus.text_downloaded_hundred_gb')), (string) (__('legacy/mybonus.text_download_note')));

        // Invite
        if ($oneinviteBonus > 0) {
            $results[] = $this->bonusItem($oneinviteBonus, 'invite', 1, (string) (__('legacy/mybonus.text_buy_invite')), (string) (__('legacy/mybonus.text_buy_invite_note')));
        }

        // Tmp Invite
        $tmpInviteBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($tmpInviteBonus > 0) {
            $results[] = $this->bonusItem($tmpInviteBonus, 'tmp_invite', 1, (string) (__('legacy/mybonus.text_buy_tmp_invite')), (string) (__('legacy/mybonus.text_buy_tmp_invite_note')));
        }

        // Custom Title
        $results[] = $this->bonusItem($customtitleBonus, 'title', 0, (string) (__('legacy/mybonus.text_custom_title')), (string) (__('legacy/mybonus.text_custom_title_note')));

        // VIP Status
        $results[] = $this->bonusItem($vipstatusBonus, 'class', 0, (string) (__('legacy/mybonus.text_vip_status')), (string) (__('legacy/mybonus.text_vip_status_note')));

        // Bonus Gift
        $giftDesc = (string) (__('legacy/mybonus.text_bonus_gift_note'));
        if ($basictaxBonus || $taxpercentageBonus) {
            $onehundredaftertax = 100 - $taxpercentageBonus - $basictaxBonus;
            $giftDesc .= '<br /><br />'.(__('legacy/mybonus.text_system_charges_receiver')).'<b>'.($basictaxBonus ? $basictaxBonus.(__('legacy/mybonus.text_tax_bonus_point')).Strings::addS($basictaxBonus).($taxpercentageBonus ? (__('legacy/mybonus.text_tax_plus')) : '') : '').($taxpercentageBonus ? $taxpercentageBonus.(__('legacy/mybonus.text_percent_of_transfered_amount')) : '').'</b>'.(__('legacy/mybonus.text_as_tax')).$onehundredaftertax.(__('legacy/mybonus.text_tax_example_note'));
        }
        $results[] = $this->bonusItem(100, 'gift_1', 0, (string) (__('legacy/mybonus.text_bonus_gift')), $giftDesc);

        // Attendance card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyAttendanceCard(), 'attendance_card', 0, (string) (__('legacy/mybonus.text_attendance_card')), (string) (__('legacy/mybonus.text_attendance_card_note')));

        // Rainbow ID
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyRainbowId(), 'rainbow_id', 0, (string) (__('legacy/mybonus.text_buy_rainbow_id')), (string) (__('legacy/mybonus.text_buy_rainbow_id_note')));

        // Change username card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyChangeUsernameCard(), 'change_username_card', 0, (string) (__('legacy/mybonus.text_buy_change_username_card')), (string) (__('legacy/mybonus.text_buy_change_username_card_note')));

        // Donate
        $results[] = $this->bonusItem(1000, 'gift_2', 0, (string) (__('legacy/mybonus.text_charity_giving')), (string) (__('legacy/mybonus.text_charity_giving_note')));

        // Cancel hit and run
        $cancelHrDesc = '<p>
            <span>'.(__('legacy/mybonus.text_cancel_hr_label')).'</span>
            <input type="number" name="hr_id" />
        </p>';
        $results[] = $this->bonusItem(BonusLogs::getBonusForCancelHitAndRun(), 'cancel_hr', 0, (string) (__('legacy/mybonus.text_cancel_hr_title')), $cancelHrDesc);

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
     * @param  array<string, mixed>  $curUser
     */
    private function resolveDoMessage(string $do, array $curUser, string $lockText): string
    {
        return match ($do) {
            'upload' => (string) (__('legacy/mybonus.text_success_upload')),
            'download' => (string) (__('legacy/mybonus.text_success_download')),
            'invite' => (string) (__('legacy/mybonus.text_success_invites')),
            'tmp_invite' => (string) (__('legacy/mybonus.text_success_tmp_invites')),
            'vip' => (string) (__('legacy/mybonus.text_success_vip')).'<b>'.UserClass::name(UC_VIP, false, false, true).'</b>'.(__('legacy/mybonus.text_success_vip_two')),
            'vipfalse' => (string) (__('legacy/mybonus.text_no_permission')),
            'title' => sprintf((string) (__('legacy/mybonus.text_success_custom_title')), (string) ($curUser['title'] ?? '')),
            'transfer' => (string) (__('legacy/mybonus.text_success_gift')),
            'charity' => (string) (__('legacy/mybonus.text_success_charity')),
            'cancel_hr' => (string) (__('legacy/mybonus.text_success_cancel_hr')),
            'buy_medal' => (string) (__('legacy/mybonus.text_success_buy_medal')),
            'attendance_card' => (string) (__('legacy/mybonus.text_success_buy_attendance_card')),
            'rainbow_id' => (string) (__('legacy/mybonus.text_success_buy_rainbow_id')),
            'change_username_card' => (string) (__('legacy/mybonus.text_success_buy_change_username_card')),
            'duplicated' => $lockText,
            default => '',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     */
    private function buildShopTable(array $allBonus, array $curUser, string $bonus, string $msg, string $lockText): string
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
        echo '<br /><b>'.(__('legacy/mybonus.text_no_buttons_note')).'</b><br /><small>('.$lockText.')</small></td></tr>';

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
                $otheroption_title = '<input type="text" name="title" maxlength="30" />';
                echo "<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name'].'</h1>'.$bonusarray['description'].'<br /><br />'.(__('legacy/mybonus.text_enter_titile')).$otheroption_title.(__('legacy/mybonus.text_click_exchange'))."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points']).'</td>';
            } elseif ($bonusarray['art'] === 'gift_1') {
                $otheroption = '<table width="100%"><tr><td class="embedded"><b>'.(__('legacy/mybonus.text_username')).'</b><input type="text" name="username" maxlength="24" /></td><td class="embedded"><b>'.(__('legacy/mybonus.text_to_be_given'))."</b><input type=\"number\" name=\"bonusgift\" id=\"giftcustom\" min='100' />".(__('legacy/mybonus.text_karma_points')).'</td></tr><tr><td class="embedded" colspan="2"><b>'.(__('legacy/mybonus.text_message')).'</b><input type="text" name="message" maxlength="100" /></td></tr></table>';
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
    private function buildInfoSection(array $curUser): string
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
        echo '<div align="center">'.(__('legacy/mybonus.text_you_are_currently_getting')).round($seedBonusResult['seed_bonus'], 3).(__('legacy/mybonus.text_point')).Strings::addS($seedBonusResult['seed_bonus']).(__('legacy/mybonus.text_per_hour')).' (A = '.round($A, 1).')</div><table align="center" border="0" width="400"><tr><td class="loadbarbg">';

        if ($percent <= 30) {
            $loadpic = 'loadbarred';
        } elseif ($percent <= 60) {
            $loadpic = 'loadbaryellow';
        } else {
            $loadpic = 'loadbargreen';
        }
        $width = $percent * 4;
        echo '<img class="'.$loadpic.'" src="pic/trans.gif" alt="'.$percent.'%" /></td></tr></table>';

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
        echo '<div>'.$bonusTableResult['table'].'</div>';

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
