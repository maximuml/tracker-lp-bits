<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\DTOs\Auth\ActorContext;
use App\Enums\BusinessType;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\BonusCalculationRepository;
use App\Repositories\BonusRepository;
use App\Repositories\MessageRepository;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Lock;
use App\Support\Log;
use App\Support\Logger;
use App\Support\PageResponses;
use App\Support\RequestValues;
use App\Support\Url;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles bonus exchange action mutations.
 * Page rendering is handled by BonusPageService.
 */
final class BonusService
{
    private const LOCK_SECONDS = 10;

    private BonusRepository $bonusRep;

    private BonusCalculationRepository $bonusCalculationRepository;

    public function __construct(BonusRepository $bonusRep, BonusCalculationRepository $bonusCalculationRepository, private readonly MessageRepository $messageRepository, private readonly BonusLedgerService $ledger)
    {
        $this->bonusRep = $bonusRep;
        $this->bonusCalculationRepository = $bonusCalculationRepository;
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     */
    public function handleExchangeActionPublic(Request $request, array $allBonus, ActorContext $actor, string $lockText): ?RedirectResponse
    {
        $action = htmlspecialchars((string) $request->query('action', ''));
        if ($action !== 'exchange') {
            return null;
        }

        if (! $request->isMethod('post')) {
            return redirect('/web/mybonus');
        }

        return $this->handleExchange($request, $allBonus, $actor, $lockText);
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     */
    public function handleExchange(Request $request, array $allBonus, ActorContext $actor, string $lockText): ?RedirectResponse
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost');
        $bonusgiftBonus = SiteConfig::current()->bonus->bonusGift() ? 'yes' : 'no';
        $ratiolimitBonus = SiteConfig::current()->bonus->ratioLimit();
        $dlamountlimitBonus = SiteConfig::current()->bonus->dlAmountLimit();
        $buyinviteClass = (int) SiteConfig::current()->authority->permission('buyinvite', 0);
        $taxpercentageBonus = SiteConfig::current()->bonus->taxPercentage();
        $basictaxBonus = SiteConfig::current()->bonus->basicTax();

        // Cheat detection
        if (
            $request->post('userid') !== null
            || $request->post('points') !== null
            || $request->post('bonus') !== null
            || $request->post('art') !== null
            || $request->post('option') === null
            || ! isset($allBonus[(int) $request->post('option', 0)])
        ) {
            Log::writeWithContext('User '.$actor->username.','.((string) $actor->value('ip', '')).' is trying to cheat at bonus system', 'mod');
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.text_cheat_alert'), true, false);
        }

        $option = (int) $request->post('option', 0);
        $bonusarray = $allBonus[$option];
        $points = (float) $bonusarray['points'];
        $userid = $actor->id;
        $art = (string) $bonusarray['art'];

        if ($actor->seedbonus() < $points) {
            return null;
        }

        $lockName = "user:{$userid}:exchange:bonus";
        $lock = new Lock($lockName, self::LOCK_SECONDS);
        if (! $lock->get()) {
            Logger::writeWithContext("[LOCKED], {$lockName}, {$lockText}", 'info', false);

            return $this->redirect($baseUrl, 'duplicated');
        }

        // trade for upload
        if ($art === 'traffic') {
            return $this->exchangeTraffic($actor, $bonusarray, $points, $ratiolimitBonus, $dlamountlimitBonus);
        }
        if ($art === 'traffic_downloaded') {
            return $this->exchangeTrafficDownloaded($actor, $bonusarray, $points, $baseUrl);
        }
        if ($art === 'class') {
            return $this->exchangeClass($actor, $points, $baseUrl);
        }
        if ($art === 'invite') {
            return $this->exchangeInvite($actor, $bonusarray, $points, $baseUrl, $buyinviteClass);
        }
        if ($art === 'tmp_invite') {
            return $this->exchangeTmpInvite($actor, $points, $baseUrl, $buyinviteClass);
        }
        if ($art === 'title') {
            return $this->exchangeTitle($request, $actor, $points, $baseUrl);
        }
        if ($art === 'gift_2') {
            return $this->exchangeCharity($request, $actor, $points, $baseUrl);
        }
        if ($art === 'gift_1' && $bonusgiftBonus === 'yes') {
            return $this->exchangeGift($request, $actor, $bonusarray, $points, $baseUrl, $taxpercentageBonus, $basictaxBonus);
        }
        if ($art === 'cancel_hr') {
            return $this->exchangeCancelHr($request, $userid, $baseUrl);
        }
        if ($art === 'attendance_card') {
            $this->bonusRep->consumeToBuyAttendanceCard($userid);

            return $this->redirect($baseUrl, 'attendance_card');
        }
        if ($art === 'rainbow_id') {
            $this->bonusRep->consumeToBuyRainbowId($userid);

            return $this->redirect($baseUrl, 'rainbow_id');
        }
        if ($art === 'change_username_card') {
            $this->bonusRep->consumeToBuyChangeUsernameCard($userid);

            return $this->redirect($baseUrl, 'change_username_card');
        }

        return null;
    }

    private function redirect(string $baseUrl, string $do): RedirectResponse
    {
        return redirect(Url::absolute($baseUrl)."/web/mybonus?do={$do}");
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    private function exchangeTraffic(ActorContext $actor, array $bonusarray, float $points, float $ratiolimitBonus, int $dlamountlimitBonus): RedirectResponse
    {
        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost');
        $uploaded = (float) $actor->value('uploaded', 0);
        $downloaded = (float) $actor->value('downloaded', 0);
        if ($uploaded > $dlamountlimitBonus * 1073741824) {
            $ratio = $downloaded > 0
                ? $uploaded / $downloaded
                : PHP_INT_MAX;
        } else {
            $ratio = 0;
        }
        if ($ratiolimitBonus > 0 && $ratio > $ratiolimitBonus) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.text_cheat_alert'), true, false);
        }
        $up = (int) $uploaded + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange uploaded from %s to %s', $actor->id, $points, $uploaded, $up), 'info', false);
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_UPLOAD, $points.' Points for uploaded.', [
            'uploaded' => DB::raw(DB::getQueryGrammar()->wrap('uploaded').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return $this->redirect($baseUrl, 'upload');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    private function exchangeTrafficDownloaded(ActorContext $actor, array $bonusarray, float $points, string $baseUrl): RedirectResponse
    {
        $down = (int) $actor->value('downloaded', 0) + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange downloaded from %s to %s', $actor->id, $points, $actor->value('downloaded', 0), $down), 'info', false);
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_DOWNLOAD, $points.' Points for downloaded.', [
            'downloaded' => DB::raw(DB::getQueryGrammar()->wrap('downloaded').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return $this->redirect($baseUrl, 'download');
    }

    private function exchangeClass(ActorContext $actor, float $points, string $baseUrl): RedirectResponse
    {
        if (UserDisplay::currentClass() >= UC_VIP) {
            PageResponses::abort(__('mybonus.std_no_permission'), __('mybonus.std_class_above_vip'), false);
        }
        $vipUntil = date('Y-m-d H:i:s', (strtotime(date('Y-m-d H:i:s')) + 28 * 86400));
        $this->ledger->debit($actor->id, $points, BusinessType::BUY_VIP, $points.' Points for 1 month VIP Status.', ['class' => UC_VIP, 'vip_added' => true, 'vip_until' => $vipUntil]);

        return $this->redirect($baseUrl, 'vip');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    private function exchangeInvite(ActorContext $actor, array $bonusarray, float $points, string $baseUrl, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            PageResponses::abort(__('mybonus.std_sorry'), UserClass::name($buyinviteClass, false, false, true).(__('mybonus.text_plus_only')), false, false);
        }
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_INVITE, $points.' Points for invites.', [
            'invites' => DB::raw(DB::getQueryGrammar()->wrap('invites').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return $this->redirect($baseUrl, 'invite');
    }

    private function exchangeTmpInvite(ActorContext $actor, float $points, string $baseUrl, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            PageResponses::abort(__('mybonus.std_sorry'), UserClass::name($buyinviteClass, false, false, true).(__('mybonus.text_plus_only')), false, false);
        }
        $this->bonusRep->consumeToBuyTemporaryInvite($actor->id);

        return $this->redirect($baseUrl, 'tmp_invite');
    }

    private function exchangeTitle(Request $request, ActorContext $actor, float $points, string $baseUrl): RedirectResponse
    {
        $title = (string) $request->post('title', '');
        $words = ['fuck', 'shit', 'pussy', 'cunt', 'nigger', 'Staff Leader', 'SysOp', 'Administrator', 'Moderator', 'Uploader', 'Retiree', 'VIP', 'Nexus Master', 'Ultimate User', 'Extreme User', 'Veteran User', 'Insane User', 'Crazy User', 'Elite User', 'Power User', 'User', 'Peasant', 'Champion'];
        $title = str_replace($words, __('mybonus.text_wasted_karma'), $title);
        $this->ledger->debit($actor->id, $points, BusinessType::CUSTOM_TITLE, $points.' Points for custom title. Old title is '.htmlspecialchars(trim((string) $actor->value('title', '')))." and new title is {$title}.", ['title' => $title]);

        return $this->redirect($baseUrl, 'title');
    }

    private function exchangeCharity(Request $request, ActorContext $actor, float $points, string $baseUrl): ?RedirectResponse
    {
        $points = (int) $request->post('bonuscharity', 0);
        if ($points < 1000 || $points > 50000) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.bonus_amount_not_allowed_two'), false);
        }
        $ratiocharity = (float) $request->post('ratiocharity', 0);
        if ($ratiocharity < 0.1 || $ratiocharity > 0.8) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.bonus_ratio_not_allowed'), false);
        }
        if ($actor->seedbonus() < $points) {
            return null;
        }
        $charityReceiverCount = $this->bonusCalculationRepository->getCharityReceiverCount($ratiocharity);
        if (! $charityReceiverCount) {
            PageResponses::abort(__('mybonus.std_sorry'), __('mybonus.std_no_users_need_charity'), false);
        }
        $senderId = $actor->id;
        $charityPerUser = $points / $charityReceiverCount;
        DB::transaction(function () use ($senderId, $points, $ratiocharity, $charityPerUser) {
            $this->ledger->debit($senderId, (float) $points, BusinessType::GIFT_TO_LOW_SHARE_RATIO, $points.' Points as charity to users with ratio below '.htmlspecialchars(trim((string) $ratiocharity)).'.', [
                'charity' => DB::raw(DB::getQueryGrammar()->wrap('charity').' + '.(float) $points), // @phpstan-ignore argument.type
            ]);
            $this->bonusRep->incrementSeedbonusForLowRatioReceivers($ratiocharity, (float) $charityPerUser);
        });

        return $this->redirect($baseUrl, 'charity');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    private function exchangeGift(Request $request, ActorContext $actor, array $bonusarray, float $points, string $baseUrl, float $taxpercentageBonus, float $basictaxBonus): ?RedirectResponse
    {
        $points = (float) $request->post('bonusgift', 0);
        $message = (string) $request->post('message', '');
        $usernamegift = trim((string) $request->post('username', ''));
        $giftReceiver = $this->bonusCalculationRepository->findGiftReceiver($usernamegift);
        if ($giftReceiver === null) {
            PageResponses::abort(__('mybonus.text_error'), view('components.my.sections.abort-receiver-not-exists')->render(), false);
        }
        $useridgift = (int) $giftReceiver->id;
        if ($points < (float) $bonusarray['points']) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.bonus_amount_not_allowed'), false);
        }
        if ($actor->seedbonus() < $points) {
            return null;
        }
        $aftertaxpoint = $points;
        if ($taxpercentageBonus) {
            $aftertaxpoint -= $aftertaxpoint * $taxpercentageBonus * 0.01;
        }
        if ($basictaxBonus) {
            $aftertaxpoint -= $basictaxBonus;
        }
        if ($aftertaxpoint <= 0) {
            PageResponses::abort(__('mybonus.text_error'), __('mybonus.bonus_amount_not_allowed'), false);
        }
        if ($actor->id === $useridgift) {
            PageResponses::abort(__('mybonus.text_huh'), view('components.my.sections.abort-karma-self')->render(), false);
        }
        $points2 = number_format($points, 1);
        $points2receiver = number_format($aftertaxpoint, 1);
        $senderId = $actor->id;
        $senderUsername = $actor->username;
        $this->ledger->transfer(
            $senderId,
            $useridgift,
            $points,
            BusinessType::GIFT_TO_SOMEONE,
            BusinessType::RECEIVE_GIFT,
            $aftertaxpoint,
            $points2.' Points as gift to '.htmlspecialchars(trim($usernamegift)),
            ' + '.$points2receiver.' Points (after tax) as a gift from '.$senderUsername,
        );

        $locale = Locale::userLocale($useridgift);
        $subject = Locale::trans('bonus.msg_someone_loves_you', [], $locale);
        $msg = Locale::trans('bonus.msg_you_have_been_given', [], $locale).$points2.Locale::trans('bonus.msg_after_tax', [], $locale).$points2receiver.Locale::trans('bonus.msg_karma_points_by', [], $locale).$actor->username;
        if ($message) {
            $msg .= "\n".Locale::trans('bonus.msg_personal_message_from', [], $locale).$actor->username.Locale::trans('bonus.msg_colon', [], $locale).$message;
        }
        $this->messageRepository->add([
            'sender' => null,
            'subject' => $subject,
            'added' => now(),
            'msg' => $msg,
            'receiver' => $useridgift,
        ]);

        return $this->redirect($baseUrl, 'transfer');
    }

    private function exchangeCancelHr(Request $request, int $userid, string $baseUrl): RedirectResponse
    {
        $hrId = $request->post('hr_id');
        if (empty($hrId)) {
            PageResponses::abort('Error', 'Invalid H&R ID: '.($hrId ?? ''), false, false);
        }
        $this->bonusRep->consumeToCancelHitAndRun($userid, $hrId);

        return $this->redirect($baseUrl, 'cancel_hr');
    }
}
