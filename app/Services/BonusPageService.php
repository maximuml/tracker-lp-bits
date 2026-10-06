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
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Strings;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\ViewModels\Bonus\BonusInfoViewModel;
use App\ViewModels\Bonus\BonusShopItem;
use App\ViewModels\Bonus\BonusShopViewModel;
use App\ViewModels\Bonus\BonusTradeButton;
use App\ViewModels\BonusPageViewModel;
use Illuminate\Contracts\View\View as ViewContract;
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
        private readonly BonusCalculationRepository $bonusCalculationRepository,
    ) {}

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): BonusPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($this->currentUser->id());

        $bonusTweak = SiteConfig::current()->tweak->bonus();
        if ($bonusTweak === 'disable' || $bonusTweak === 'disablesave') {
            LegacyResponse::abort(
                (string) (__('legacy/mybonus.std_sorry')),
                view('my.sections._bonus_disabled', [
                    'disabled' => (string) (__('legacy/mybonus.std_karma_system_disabled')),
                    'pointsActive' => $bonusTweak === 'disablesave' ? (string) (__('legacy/mybonus.std_points_active')) : null,
                ])->render(),
                false
            );
        }

        $lockSeconds = 10;
        $lockText = sprintf((string) (__('legacy/mybonus.lock_text')), $lockSeconds);

        $allBonus = $this->buildBonusArray();

        $action = htmlspecialchars((string) $request->query('action', ''));
        $do = htmlspecialchars((string) $request->query('do', ''));

        $msg = $this->resolveDoMessage($do, $curUser, $lockText);

        $bonus = number_format((float) ($this->currentUser->seedbonus()), 1);

        $shop = null;
        $info = null;
        if (! $action) {
            $shop = $this->buildShop($allBonus, $curUser, $bonus, $msg, $lockText);
            $info = $this->buildInfo($curUser);
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
            shop: $shop,
            info: $info,
            sitename: SiteConfig::current()->basic->siteName(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildBonusArray(): array
    {
        $onegbuploadBonus = SiteConfig::current()->bonus->oneGbUpload();
        $fivegbuploadBonus = SiteConfig::current()->bonus->fiveGbUpload();
        $tengbuploadBonus = SiteConfig::current()->bonus->tenGbUpload();
        $oneinviteBonus = SiteConfig::current()->bonus->oneInvite();
        $customtitleBonus = SiteConfig::current()->bonus->customTitle();
        $vipstatusBonus = SiteConfig::current()->bonus->vipStatus();
        $basictaxBonus = SiteConfig::current()->bonus->basicTax();
        $taxpercentageBonus = SiteConfig::current()->bonus->taxPercentage();

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
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->tenGbDownload(), 'traffic_downloaded', 10737418240, view('my.sections._data-color', ['pre' => (string) (__('legacy/mybonus.text_downloaded_ten_gb')), 'color' => '#ff4500', 'text' => (string) (__('legacy/mybonus.text_downloaded_label'))]), view('my.sections._download-note'));
        // 100.0 GB Downloaded
        $results[] = $this->bonusItem((float) SiteConfig::current()->bonus->hundredGbDownload(), 'traffic_downloaded', 107374182400, view('my.sections._data-color', ['pre' => (string) (__('legacy/mybonus.text_downloaded_hundred_gb')), 'color' => '#ff4500', 'text' => (string) (__('legacy/mybonus.text_downloaded_label'))]), view('my.sections._download-note'));

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
        $giftTax = null;
        if ($basictaxBonus || $taxpercentageBonus) {
            $onehundredaftertax = 100 - $taxpercentageBonus - $basictaxBonus;
            $giftTax = [
                'charges' => view('my.sections._gift-charges'),
                'amounts' => ($basictaxBonus ? $basictaxBonus.(__('legacy/mybonus.text_tax_bonus_point')).Strings::addS($basictaxBonus).($taxpercentageBonus ? (__('legacy/mybonus.text_tax_plus')) : '') : '').($taxpercentageBonus ? $taxpercentageBonus.(__('legacy/mybonus.text_percent_of_transfered_amount')) : ''),
                'rest' => (__('legacy/mybonus.text_as_tax')).$onehundredaftertax.(__('legacy/mybonus.text_tax_example_note')),
            ];
        }
        $gift = $this->bonusItem(100, 'gift_1', 0, (string) (__('legacy/mybonus.text_bonus_gift')), (string) (__('legacy/mybonus.text_bonus_gift_note')));
        $gift['giftTax'] = $giftTax;
        $results[] = $gift;

        // Attendance card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyAttendanceCard(), 'attendance_card', 0, (string) (__('legacy/mybonus.text_attendance_card')), (string) (__('legacy/mybonus.text_attendance_card_note')));

        // Rainbow ID
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyRainbowId(), 'rainbow_id', 0, (string) (__('legacy/mybonus.text_buy_rainbow_id')), (string) (__('legacy/mybonus.text_buy_rainbow_id_note')));

        // Change username card
        $results[] = $this->bonusItem(BonusLogs::getBonusForBuyChangeUsernameCard(), 'change_username_card', 0, (string) (__('legacy/mybonus.text_buy_change_username_card')), (string) (__('legacy/mybonus.text_buy_change_username_card_note')));

        // Donate
        $results[] = $this->bonusItem(1000, 'gift_2', 0, (string) (__('legacy/mybonus.text_charity_giving')), (string) (__('legacy/mybonus.text_charity_giving_note')));

        // Cancel hit and run
        $results[] = $this->bonusItem(BonusLogs::getBonusForCancelHitAndRun(), 'cancel_hr', 0, (string) (__('legacy/mybonus.text_cancel_hr_title')), '');

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function bonusItem(float $points, string $art, int $menge, string|ViewContract $name, string|ViewContract $description): array
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
            'upload' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_upload')), 'b' => (string) (__('legacy/mybonus.text_uploaded_amount')), 'post' => '!'])->render(),
            'download' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_download')), 'b' => (string) (__('legacy/mybonus.text_downloaded_amount')), 'post' => '!'])->render(),
            'invite' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_invites')), 'b' => '1', 'post' => (string) (__('legacy/mybonus.text_new_invite'))])->render(),
            'tmp_invite' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_tmp_invites')), 'b' => '1', 'post' => (string) (__('legacy/mybonus.text_new_tmp_invite'))])->render(),
            'vip' => view('my.sections._vip_msg', [
                'pre' => (string) (__('legacy/mybonus.text_success_vip')),
                'name' => UserClass::name(UC_VIP, false, false, true),
                'post' => (string) (__('legacy/mybonus.text_success_vip_two')),
            ])->render(),
            'vipfalse' => view('my.sections._b-msg', ['pre' => '', 'b' => (string) (__('legacy/mybonus.text_error_bang')), 'post' => (string) (__('legacy/mybonus.text_no_permission'))])->render(),
            'title' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_custom_title')), 'b' => (string) ($this->currentUser->value('title', '')), 'post' => '!'])->render(),
            'transfer' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_gift')), 'b' => (string) (__('legacy/mybonus.text_karma')), 'post' => (string) (__('legacy/mybonus.text_karma_well'))])->render(),
            'charity' => (string) (__('legacy/mybonus.text_success_charity')),
            'cancel_hr' => (string) (__('legacy/mybonus.text_success_cancel_hr')),
            'attendance_card' => (string) (__('legacy/mybonus.text_success_buy_attendance_card')),
            'rainbow_id' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_buy_rainbow_id')), 'b' => '30', 'post' => (string) (__('legacy/mybonus.text_rainbow_days'))])->render(),
            'change_username_card' => view('my.sections._b-msg', ['pre' => (string) (__('legacy/mybonus.text_success_buy_change_username_card')), 'b' => (string) (__('legacy/mybonus.text_change_username_card')), 'post' => '!'])->render(),
            'duplicated' => $lockText,
            default => '',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     */
    private function buildShop(array $allBonus, array $curUser, string $bonus, string $msg, string $lockText): BonusShopViewModel
    {
        $bonusgiftBonus = SiteConfig::current()->bonus->bonusGift() ? 'yes' : 'no';
        $ratiolimitBonus = SiteConfig::current()->bonus->ratioLimit();
        $dlamountlimitBonus = SiteConfig::current()->bonus->dlAmountLimit();

        $items = [];
        for ($i = 0; $i < count($allBonus); $i++) {
            $bonusarray = $allBonus[$i];
            if (
                ($bonusarray['art'] === 'gift_1' && $bonusgiftBonus === 'no')
                || ($bonusarray['art'] === 'cancel_hr' && ! HitAndRun::getIsEnabled())
            ) {
                continue;
            }

            $art = (string) $bonusarray['art'];
            $pointsLabel2 = $art === 'gift_2' ? (__('legacy/mybonus.text_max')).'50,000' : null;
            $pointsLabel = match ($art) {
                'gift_1' => SafeHtml::fromUntrustedHtml(__('legacy/mybonus.text_min').'100'),
                'gift_2' => SafeHtml::fromUntrustedHtml(__('legacy/mybonus.text_min').'1,000'),
                default => SafeHtml::fromUntrustedHtml(number_format((float) $bonusarray['points'])),
            };

            $affordable = ($this->currentUser->seedbonus()) >= $bonusarray['points'];
            $trade = $affordable
                ? $this->resolveTradeButton($art, $curUser, $ratiolimitBonus, $dlamountlimitBonus)
                : $this->tradeButton('legacy/mybonus.text_more_points_needed', true);

            $items[] = new BonusShopItem(
                index: $i,
                art: $art,
                name: $bonusarray['name'] instanceof ViewContract
                    ? SafeHtml::fromTrustedHtml($bonusarray['name']->render())
                    : SafeHtml::fromUntrustedHtml((string) $bonusarray['name']),
                description: $bonusarray['description'] instanceof ViewContract
                    ? SafeHtml::fromTrustedHtml($bonusarray['description']->render())
                    : SafeHtml::fromUntrustedHtml((string) $bonusarray['description']),
                pointsLabel: $pointsLabel,
                trade: $trade,
                pointsLabel2: $pointsLabel2,
                giftTax: $bonusarray['giftTax'] ?? null,
            );
        }

        return new BonusShopViewModel(
            sitename: SiteConfig::current()->basic->siteName(),
            msg: SafeHtml::fromUntrustedHtml($msg),
            bonus: $bonus,
            lockText: $lockText,
            items: $items,
        );
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function resolveTradeButton(string $art, array $curUser, float $ratiolimitBonus, int $dlamountlimitBonus): BonusTradeButton
    {
        if ($art === 'gift_1') {
            return $this->tradeButton('legacy/mybonus.submit_karma_gift', false);
        }
        if ($art === 'gift_2') {
            return $this->tradeButton('legacy/mybonus.submit_charity_giving', false);
        }
        if ($art === 'invite' || $art === 'tmp_invite') {
            if (! SiteConfig::current()->main->inviteSystem()) {
                return $this->tradeButtonRaw((string) Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null), true);
            }
            if (! Permission::can(PermissionEnum::SEND_INVITE)) {
                $requireClass = SiteConfig::current()->authority->permission(PermissionEnum::SEND_INVITE->value);

                return $this->tradeButtonRaw((string) Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText($requireClass ?? 0)], null), true);
            }

            return $this->tradeButton('legacy/mybonus.submit_exchange', false);
        }
        if ($art === 'class') {
            if (UserDisplay::currentClass() >= UC_VIP) {
                return $this->tradeButton('legacy/mybonus.std_class_above_vip', true);
            }

            return $this->tradeButton('legacy/mybonus.submit_exchange', false);
        }
        if ($art === 'traffic') {
            if (($this->currentUser->value('downloaded', 0)) > 0) {
                if (($this->currentUser->value('uploaded', 0)) > $dlamountlimitBonus * 1073741824) {
                    $ratio = ($this->currentUser->value('uploaded', 0)) / ($this->currentUser->value('downloaded', 1));
                } else {
                    $ratio = 0;
                }
            } else {
                $ratio = $ratiolimitBonus + 1;
            }
            if ($ratiolimitBonus > 0 && $ratio > $ratiolimitBonus) {
                return $this->tradeButton('legacy/mybonus.text_ratio_too_high', true);
            }

            return $this->tradeButton('legacy/mybonus.submit_exchange', false);
        }
        if ($art === 'change_username_card') {
            if ($this->bonusCalculationRepository->hasChangeUsernameCard((int) ($this->currentUser->id()))) {
                return $this->tradeButton('legacy/mybonus.text_change_username_card_already_has', true);
            }

            return $this->tradeButton('legacy/mybonus.submit_exchange', false);
        }
        if ($art === 'rainbow_id') {
            if ($this->bonusCalculationRepository->hasRainbowIdForever((int) ($this->currentUser->id()))) {
                return $this->tradeButton('legacy/mybonus.text_rainbow_id_already_valid_forever', true);
            }

            return $this->tradeButton('legacy/mybonus.submit_exchange', false);
        }

        return $this->tradeButton('legacy/mybonus.submit_exchange', false);
    }

    /**
     * Legacy submit labels may carry HTML entities (`more&nbsp;points
     * needed`) — the old code echoed them raw into `value="..."`, so the
     * browser decoded them. Decode here so `{{ }}` escaping stays on.
     */
    private function tradeButton(string $langKey, bool $disabled): BonusTradeButton
    {
        return $this->tradeButtonRaw((string) __($langKey), $disabled);
    }

    private function tradeButtonRaw(string $label, bool $disabled): BonusTradeButton
    {
        return new BonusTradeButton(html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $disabled);
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildInfo(array $curUser): BonusInfoViewModel
    {
        $perseedingBonus = SiteConfig::current()->bonus->perSeeding();
        $maxseedingBonus = SiteConfig::current()->bonus->maxSeeding();
        $bzeroBonus = SiteConfig::current()->bonus->bZero();

        $seedBonusResult = Bonus::calculateForUser((int) ($this->currentUser->id()), null);
        $bonusTableResult = Bonus::buildBonusTableForUser($curUser, $seedBonusResult, ['table_style' => 'width: 50%']);

        $percent = $seedBonusResult['seed_bonus'] * 100 / ($bzeroBonus + $perseedingBonus * $maxseedingBonus);
        $loadpic = $percent <= 30 ? 'loadbarred' : ($percent <= 60 ? 'loadbaryellow' : 'loadbargreen');

        $minSize = SiteConfig::current()->bonus->minSize();

        return new BonusInfoViewModel(
            perseedingBonus: $perseedingBonus,
            maxseedingBonus: $maxseedingBonus,
            tzeroBonus: SiteConfig::current()->bonus->tZero(),
            nzeroBonus: SiteConfig::current()->bonus->nZero(),
            zeroBonusFactor: (float) SiteConfig::current()->bonus->zeroBonusFactor(),
            bzeroBonus: $bzeroBonus,
            lBonus: SiteConfig::current()->bonus->l(),
            minSizeLine: $minSize > 0 ? sprintf((string) (__('legacy/mybonus.text_bonus_mini_size')), Format::size($minSize)) : null,
            donortimesBonus: SiteConfig::current()->bonus->donorTimes(),
            currentSeedBonus: (string) round((float) $seedBonusResult['seed_bonus'], 3),
            aFactor: (string) round((float) $seedBonusResult['A'], 1),
            percentLabel: (string) $percent,
            loadbarClass: $loadpic,
            userId: (int) ($this->currentUser->id()),
            officialAdditionFactor: $bonusTableResult['has_official_addition'] ? (string) $bonusTableResult['official_addition_factor'] : null,
            haremAdditionFactor: $bonusTableResult['has_harem_addition'] ? (string) $bonusTableResult['harem_addition_factor'] : null,
            summaryTable: SafeHtml::fromTrustedHtml((string) $bonusTableResult['table']),
            uploadtorrentBonus: (float) SiteConfig::current()->bonus->uploadTorrent(),
            starttopicBonus: SiteConfig::current()->bonus->startTopic(),
            makepostBonus: SiteConfig::current()->bonus->makePost(),
            addcommentBonus: SiteConfig::current()->bonus->addComment(),
            pollvoteBonus: SiteConfig::current()->bonus->pollVote(),
            offervoteBonus: SiteConfig::current()->bonus->offerVote(),
            saythanksBonus: SiteConfig::current()->bonus->sayThanks(),
            receivethanksBonus: SiteConfig::current()->bonus->receiveThanks(),
            ratiolimitBonus: SiteConfig::current()->bonus->ratioLimit(),
            dlamountlimitBonus: SiteConfig::current()->bonus->dlAmountLimit(),
        );
    }
}
