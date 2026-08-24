<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\Message;
use App\Repositories\BonusRepository;
use App\Support\Html;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusLock;

/**
 * Handles bonus exchange action mutations.
 * Page rendering is handled by BonusPageService.
 */
final class BonusService
{
    private const LOCK_SECONDS = 10;

    private BonusRepository $bonusRep;

    public function __construct(BonusRepository $bonusRep)
    {
        $this->bonusRep = $bonusRep;
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    public function handleExchangeActionPublic(Request $request, array $allBonus, array $curUser, array $lang, string $lockText): ?RedirectResponse
    {
        $action = htmlspecialchars((string) $request->query('action', ''));
        if ($action !== 'exchange') {
            return null;
        }

        return $this->handleExchange($request, $allBonus, $curUser, $lang, $lockText);
    }

    /**
     * @param  array<int, array<string, mixed>>  $allBonus
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function handleExchange(Request $request, array $allBonus, array $curUser, array $lang, string $lockText): ?RedirectResponse
    {
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');
        $bonusgiftBonus = (string) SupportContext::getGlobal('bonusgift_bonus', 'yes');
        $ratiolimitBonus = (float) SupportContext::getGlobal('ratiolimit_bonus', 0);
        $dlamountlimitBonus = (int) SupportContext::getGlobal('dlamountlimit_bonus', 0);
        $buyinviteClass = (int) SupportContext::getGlobal('buyinvite_class', 0);
        $taxpercentageBonus = (float) SupportContext::getGlobal('taxpercentage_bonus', 0);
        $basictaxBonus = (float) SupportContext::getGlobal('basictax_bonus', 0);

        // Cheat detection
        if (
            $request->post('userid') !== null
            || $request->post('points') !== null
            || $request->post('bonus') !== null
            || $request->post('art') !== null
            || $request->post('option') === null
            || ! isset($allBonus[(int) $request->post('option', 0)])
        ) {
            Log::writeWithContext('User '.($curUser['username'] ?? '').','.($curUser['ip'] ?? '').' is trying to cheat at bonus system', 'mod');
            LegacyResponse::abort((string) ($lang['text_error'] ?? ''), (string) ($lang['text_cheat_alert'] ?? ''), true, false);
        }

        $option = (int) $request->post('option', 0);
        $bonusarray = $allBonus[$option];
        $points = (float) $bonusarray['points'];
        $userid = (int) ($curUser['id'] ?? 0);
        $art = (string) $bonusarray['art'];

        if (($curUser['seedbonus'] ?? 0) < $points) {
            return null;
        }

        $lockName = "user:{$userid}:exchange:bonus";
        $lock = new NexusLock($lockName, self::LOCK_SECONDS);
        if (! $lock->get()) {
            Logger::writeWithContext("[LOCKED], {$lockName}, {$lockText}", 'info', false);

            return $this->redirect($baseUrl, 'duplicated');
        }

        // trade for upload
        if ($art === 'traffic') {
            return $this->exchangeTraffic($curUser, $bonusarray, $points, $ratiolimitBonus, $dlamountlimitBonus, $lang);
        }
        if ($art === 'traffic_downloaded') {
            return $this->exchangeTrafficDownloaded($curUser, $bonusarray, $points, $baseUrl);
        }
        if ($art === 'class') {
            return $this->exchangeClass($curUser, $points, $baseUrl, $lang);
        }
        if ($art === 'invite') {
            return $this->exchangeInvite($curUser, $bonusarray, $points, $baseUrl, $lang, $buyinviteClass);
        }
        if ($art === 'tmp_invite') {
            return $this->exchangeTmpInvite($curUser, $points, $baseUrl, $lang, $buyinviteClass);
        }
        if ($art === 'title') {
            return $this->exchangeTitle($request, $curUser, $points, $baseUrl, $lang);
        }
        if ($art === 'gift_2') {
            return $this->exchangeCharity($request, $curUser, $points, $baseUrl, $lang);
        }
        if ($art === 'gift_1' && $bonusgiftBonus === 'yes') {
            return $this->exchangeGift($request, $curUser, $bonusarray, $points, $baseUrl, $lang, $taxpercentageBonus, $basictaxBonus);
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
        return redirect(Http::protocolPrefix(Url::isSecure()).$baseUrl."/mybonus.php?do={$do}");
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $bonusarray
     * @param  array<string, mixed>  $lang
     */
    private function exchangeTraffic(array $curUser, array $bonusarray, float $points, float $ratiolimitBonus, int $dlamountlimitBonus, array $lang): RedirectResponse
    {
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');
        if (($curUser['uploaded'] ?? 0) > $dlamountlimitBonus * 1073741824) {
            $ratio = ($curUser['downloaded'] ?? 0) > 0
                ? ($curUser['uploaded'] ?? 0) / ($curUser['downloaded'] ?? 1)
                : PHP_INT_MAX;
        } else {
            $ratio = 0;
        }
        if ($ratiolimitBonus > 0 && $ratio > $ratiolimitBonus) {
            LegacyResponse::abort((string) ($lang['text_error'] ?? ''), (string) ($lang['text_cheat_alert'] ?? ''), true, false);
        }
        $up = (int) ($curUser['uploaded'] ?? 0) + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange uploaded from %s to %s', $curUser['id'] ?? 0, $points, $curUser['uploaded'] ?? 0, $up), 'info', false);
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points.' Points for uploaded.', ['uploaded' => $up]);

        return $this->redirect($baseUrl, 'upload');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $bonusarray
     */
    private function exchangeTrafficDownloaded(array $curUser, array $bonusarray, float $points, string $baseUrl): RedirectResponse
    {
        $down = (int) ($curUser['downloaded'] ?? 0) + (int) $bonusarray['menge'];
        Logger::writeWithContext(sprintf('user: %s going to use %s bonus to exchange downloaded from %s to %s', $curUser['id'] ?? 0, $points, $curUser['downloaded'] ?? 0, $down), 'info', false);
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD, $points.' Points for downloaded.', ['downloaded' => $down]);

        return $this->redirect($baseUrl, 'download');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function exchangeClass(array $curUser, float $points, string $baseUrl, array $lang): ?RedirectResponse
    {
        if (UserDisplay::currentClass() >= UC_VIP) {
            Html::stdMessage((string) ($lang['std_no_permission'] ?? ''), (string) ($lang['std_class_above_vip'] ?? ''), false);

            return null;
        }
        $vipUntil = date('Y-m-d H:i:s', (strtotime(date('Y-m-d H:i:s')) + 28 * 86400));
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_BUY_VIP, $points.' Points for 1 month VIP Status.', ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vipUntil]);

        return $this->redirect($baseUrl, 'vip');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $bonusarray
     * @param  array<string, mixed>  $lang
     */
    private function exchangeInvite(array $curUser, array $bonusarray, float $points, string $baseUrl, array $lang, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            LegacyResponse::abort((string) ($lang['std_sorry'] ?? ''), UserClass::name($buyinviteClass, false, false, true).($lang['text_plus_only'] ?? ''), false, false);
        }
        $inv = (int) ($curUser['invites'] ?? 0) + (int) $bonusarray['menge'];
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points.' Points for invites.', ['invites' => $inv]);

        return $this->redirect($baseUrl, 'invite');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function exchangeTmpInvite(array $curUser, float $points, string $baseUrl, array $lang, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            LegacyResponse::abort((string) ($lang['std_sorry'] ?? ''), UserClass::name($buyinviteClass, false, false, true).($lang['text_plus_only'] ?? ''), false, false);
        }
        $this->bonusRep->consumeToBuyTemporaryInvite((int) $curUser['id']);

        return $this->redirect($baseUrl, 'tmp_invite');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function exchangeTitle(Request $request, array $curUser, float $points, string $baseUrl, array $lang): RedirectResponse
    {
        $title = (string) $request->post('title', '');
        $words = ['fuck', 'shit', 'pussy', 'cunt', 'nigger', 'Staff Leader', 'SysOp', 'Administrator', 'Moderator', 'Uploader', 'Retiree', 'VIP', 'Nexus Master', 'Ultimate User', 'Extreme User', 'Veteran User', 'Insane User', 'Crazy User', 'Elite User', 'Power User', 'User', 'Peasant', 'Champion'];
        $title = str_replace($words, (string) ($lang['text_wasted_karma'] ?? ''), $title);
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points.' Points for custom title. Old title is '.htmlspecialchars(trim((string) ($curUser['title'] ?? '')))." and new title is {$title}.", ['title' => $title]);

        return $this->redirect($baseUrl, 'title');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $lang
     */
    private function exchangeCharity(Request $request, array $curUser, float $points, string $baseUrl, array $lang): ?RedirectResponse
    {
        $points = (int) $request->post('bonuscharity', 0);
        if ($points < 1000 || $points > 50000) {
            Html::stdMessage((string) ($lang['text_error'] ?? ''), (string) ($lang['bonus_amount_not_allowed_two'] ?? ''), false);

            return null;
        }
        $ratiocharity = (float) $request->post('ratiocharity', 0);
        if ($ratiocharity < 0.1 || $ratiocharity > 0.8) {
            Html::stdMessage((string) ($lang['text_error'] ?? ''), (string) ($lang['bonus_ratio_not_allowed'] ?? ''));

            return null;
        }
        if (($curUser['seedbonus'] ?? 0) < $points) {
            return null;
        }
        $charityReceiverCount = $this->bonusRep->getCharityReceiverCount($ratiocharity);
        if (! $charityReceiverCount) {
            Html::stdMessage((string) ($lang['std_sorry'] ?? ''), (string) ($lang['std_no_users_need_charity'] ?? ''));

            return null;
        }
        $this->bonusRep->consumeUserBonusAndIncrementCharity((int) $curUser['id'], (float) $points, BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $points.' Points as charity to users with ratio below '.htmlspecialchars(trim((string) $ratiocharity)).'.', (float) $points);
        $charityPerUser = $points / $charityReceiverCount;
        $this->bonusRep->incrementSeedbonusForLowRatioReceivers($ratiocharity, (float) $charityPerUser);

        return $this->redirect($baseUrl, 'charity');
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $bonusarray
     * @param  array<string, mixed>  $lang
     */
    private function exchangeGift(Request $request, array $curUser, array $bonusarray, float $points, string $baseUrl, array $lang, float $taxpercentageBonus, float $basictaxBonus): ?RedirectResponse
    {
        $points = (float) $request->post('bonusgift', 0);
        $message = (string) $request->post('message', '');
        $usernamegift = trim((string) $request->post('username', ''));
        $arr = $this->bonusRep->findGiftReceiver($usernamegift);
        if (empty($arr)) {
            Html::stdMessage((string) ($lang['text_error'] ?? ''), (string) ($lang['text_receiver_not_exists'] ?? ''), false);

            return null;
        }
        $useridgift = (int) $arr['id'];
        $userseedbonus = (float) $arr['seedbonus'];
        if ($points < (float) $bonusarray['points']) {
            Html::stdMessage((string) ($lang['text_error'] ?? ''), (string) ($lang['bonus_amount_not_allowed'] ?? ''));

            return null;
        }
        if (($curUser['seedbonus'] ?? 0) < $points) {
            return null;
        }
        $aftertaxpoint = $points;
        if ($taxpercentageBonus) {
            $aftertaxpoint -= $aftertaxpoint * $taxpercentageBonus * 0.01;
        }
        if ($basictaxBonus) {
            $aftertaxpoint -= $basictaxBonus;
        }
        if ((int) $curUser['id'] === $useridgift) {
            Html::stdMessage((string) ($lang['text_huh'] ?? ''), (string) ($lang['text_karma_self_giving_warning'] ?? ''), false);

            return null;
        }
        $points2 = number_format($points, 1);
        $points2receiver = number_format($aftertaxpoint, 1);
        $this->bonusRep->consumeUserBonus((int) $curUser['id'], $points, BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $points2.' Points as gift to '.htmlspecialchars(trim($usernamegift)));
        $this->bonusRep->incrementUserSeedbonus($useridgift, (float) $aftertaxpoint);
        BonusLogs::add($useridgift, $userseedbonus, $aftertaxpoint, $userseedbonus + $aftertaxpoint, ' + '.$points2receiver.' Points (after tax) as a gift from '.($curUser['username'] ?? ''), BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT);

        $locale = Locale::userLocale($useridgift);
        $subject = Locale::trans('bonus.msg_someone_loves_you', [], $locale);
        $msg = Locale::trans('bonus.msg_you_have_been_given', [], $locale).$points2.Locale::trans('bonus.msg_after_tax', [], $locale).$points2receiver.Locale::trans('bonus.msg_karma_points_by', [], $locale).($curUser['username'] ?? '');
        if ($message) {
            $msg .= "\n".Locale::trans('bonus.msg_personal_message_from', [], $locale).($curUser['username'] ?? '').Locale::trans('bonus.msg_colon', [], $locale).$message;
        }
        Message::add([
            'sender' => 0,
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
            LegacyResponse::abort('Error', 'Invalid H&R ID: '.($hrId ?? ''), false, false);
        }
        $this->bonusRep->consumeToCancelHitAndRun($userid, $hrId);

        return $this->redirect($baseUrl, 'cancel_hr');
    }
}
