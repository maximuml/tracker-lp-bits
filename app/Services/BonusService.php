<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Services\Bonus\Exchanges\MybonusRedirect;
use App\Services\Bonus\GiftExchangeService;
use App\Services\Bonus\PerkExchangeService;
use App\Services\Bonus\TrafficExchangeService;
use App\Support\Config\SiteConfig;
use App\Support\Lock;
use App\Support\Log;
use App\Support\Logger;
use App\Support\PageResponses;
use App\Support\RequestValues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bonus exchange dispatcher: validates the request, guards against
 * duplicates with a lock, and hands off to the exchange services under
 * {@see Bonus}. Page rendering is handled by
 * BonusPageService.
 */
final class BonusService
{
    private const LOCK_SECONDS = 10;

    public function __construct(
        private readonly BonusPurchaseService $purchase,
        private readonly TrafficExchangeService $trafficExchange,
        private readonly PerkExchangeService $perkExchange,
        private readonly GiftExchangeService $giftExchange,
    ) {}

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

            return MybonusRedirect::to($baseUrl, 'duplicated');
        }

        if ($art === 'traffic') {
            return $this->trafficExchange->exchangeUpload($actor, $bonusarray, $points, $baseUrl, $ratiolimitBonus, $dlamountlimitBonus);
        }
        if ($art === 'traffic_downloaded') {
            return $this->trafficExchange->exchangeDownload($actor, $bonusarray, $points, $baseUrl);
        }
        if ($art === 'class') {
            return $this->perkExchange->buyVipStatus($actor, $points, $baseUrl);
        }
        if ($art === 'invite') {
            return $this->perkExchange->buyInvites($actor, $bonusarray, $points, $baseUrl, $buyinviteClass);
        }
        if ($art === 'tmp_invite') {
            return $this->perkExchange->buyTemporaryInvite($actor, $points, $baseUrl, $buyinviteClass);
        }
        if ($art === 'title') {
            return $this->perkExchange->buyCustomTitle($request, $actor, $points, $baseUrl);
        }
        if ($art === 'gift_2') {
            return $this->giftExchange->sendCharity($request, $actor, $points, $baseUrl);
        }
        if ($art === 'gift_1' && $bonusgiftBonus === 'yes') {
            return $this->giftExchange->sendGift($request, $actor, $bonusarray, $points, $baseUrl, $taxpercentageBonus, $basictaxBonus);
        }
        if ($art === 'cancel_hr') {
            $hrId = $request->post('hr_id');
            if (empty($hrId)) {
                PageResponses::abort('Error', 'Invalid H&R ID: '.($hrId ?? ''), false, false);
            }
            $this->purchase->consumeToCancelHitAndRun($userid, $hrId);

            return MybonusRedirect::to($baseUrl, 'cancel_hr');
        }
        if ($art === 'attendance_card') {
            $this->purchase->consumeToBuyAttendanceCard($userid);

            return MybonusRedirect::to($baseUrl, 'attendance_card');
        }
        if ($art === 'rainbow_id') {
            $this->purchase->consumeToBuyRainbowId($userid);

            return MybonusRedirect::to($baseUrl, 'rainbow_id');
        }
        if ($art === 'change_username_card') {
            $this->purchase->consumeToBuyChangeUsernameCard($userid);

            return MybonusRedirect::to($baseUrl, 'change_username_card');
        }

        return null;
    }
}
