<?php

declare(strict_types=1);

namespace App\Services\Bonus;

use App\DTOs\Auth\ActorContext;
use App\Enums\BusinessType;
use App\Repositories\BonusCalculationRepository;
use App\Repositories\BonusConsumptionRepository;
use App\Repositories\MessageRepository;
use App\Services\Bonus\Exchanges\MybonusRedirect;
use App\Services\BonusLedgerService;
use App\Support\Locale;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bonus transfers to other users: charity distribution to low-ratio
 * members and direct gifts with tax. Returns null when the balance check
 * inside the flow fails — the dispatcher then re-renders the page.
 */
final class GiftExchangeService
{
    public function __construct(
        private readonly BonusLedgerService $ledger,
        private readonly BonusCalculationRepository $bonusCalculationRepository,
        private readonly BonusConsumptionRepository $consumptionRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function sendCharity(Request $request, ActorContext $actor, float $points, string $baseUrl): ?RedirectResponse
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
            $this->consumptionRepository->incrementSeedbonusForLowRatioReceivers($ratiocharity, (float) $charityPerUser);
        });

        return MybonusRedirect::to($baseUrl, 'charity');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    public function sendGift(Request $request, ActorContext $actor, array $bonusarray, float $points, string $baseUrl, float $taxpercentageBonus, float $basictaxBonus): ?RedirectResponse
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

        return MybonusRedirect::to($baseUrl, 'transfer');
    }
}
