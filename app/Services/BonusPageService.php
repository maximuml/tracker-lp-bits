<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BonusLogs;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\Support\Strings;
use App\Support\UserClass;
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
        private readonly BonusShopSectionBuilder $shop,
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
            $shopHtml = $this->shop->buildShopTable($allBonus, $curUser, $bonus, $msg, $lockText);
            $infoHtml = $this->shop->buildInfoSection($curUser);
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
            <span style="">'.(__('legacy/mybonus.text_cancel_hr_label')).'</span>
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
}
