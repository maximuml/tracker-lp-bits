<?php

declare(strict_types=1);

namespace App\Services\Bonus;

use App\Auth\Permission;
use App\DTOs\Auth\ActorContext;
use App\Enums\BusinessType;
use App\Enums\Permission\PermissionEnum;
use App\Services\Bonus\Exchanges\MybonusRedirect;
use App\Services\BonusLedgerService;
use App\Services\BonusPurchaseService;
use App\Support\PageResponses;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bonus-for-perk exchanges: VIP status, invites, custom title — purchases
 * that mutate the buyer's own account.
 */
final class PerkExchangeService
{
    public function __construct(
        private readonly BonusLedgerService $ledger,
        private readonly BonusPurchaseService $purchase,
    ) {}

    public function buyVipStatus(ActorContext $actor, float $points, string $baseUrl): RedirectResponse
    {
        if (UserDisplay::currentClass() >= UC_VIP) {
            PageResponses::abort(__('mybonus.std_no_permission'), __('mybonus.std_class_above_vip'), false);
        }
        $vipUntil = date('Y-m-d H:i:s', (strtotime(date('Y-m-d H:i:s')) + 28 * 86400));
        $this->ledger->debit($actor->id, $points, BusinessType::BUY_VIP, $points.' Points for 1 month VIP Status.', ['class' => UC_VIP, 'vip_added' => true, 'vip_until' => $vipUntil]);

        return MybonusRedirect::to($baseUrl, 'vip');
    }

    /**
     * @param  array<string, mixed>  $bonusarray
     */
    public function buyInvites(ActorContext $actor, array $bonusarray, float $points, string $baseUrl, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            PageResponses::abort(__('mybonus.std_sorry'), UserClass::name($buyinviteClass, false, false, true).(__('mybonus.text_plus_only')), false, false);
        }
        $this->ledger->debit($actor->id, $points, BusinessType::EXCHANGE_INVITE, $points.' Points for invites.', [
            'invites' => DB::raw(DB::getQueryGrammar()->wrap('invites').' + '.(int) $bonusarray['menge']), // @phpstan-ignore argument.type
        ]);

        return MybonusRedirect::to($baseUrl, 'invite');
    }

    public function buyTemporaryInvite(ActorContext $actor, float $points, string $baseUrl, int $buyinviteClass): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::BUY_INVITE)) {
            PageResponses::abort(__('mybonus.std_sorry'), UserClass::name($buyinviteClass, false, false, true).(__('mybonus.text_plus_only')), false, false);
        }
        $this->purchase->consumeToBuyTemporaryInvite($actor->id);

        return MybonusRedirect::to($baseUrl, 'tmp_invite');
    }

    public function buyCustomTitle(Request $request, ActorContext $actor, float $points, string $baseUrl): RedirectResponse
    {
        $title = (string) $request->post('title', '');
        $words = ['fuck', 'shit', 'pussy', 'cunt', 'nigger', 'Staff Leader', 'SysOp', 'Administrator', 'Moderator', 'Uploader', 'Retiree', 'VIP', 'Nexus Master', 'Ultimate User', 'Extreme User', 'Veteran User', 'Insane User', 'Crazy User', 'Elite User', 'Power User', 'User', 'Peasant', 'Champion'];
        $title = str_replace($words, __('mybonus.text_wasted_karma'), $title);
        $this->ledger->debit($actor->id, $points, BusinessType::CUSTOM_TITLE, $points.' Points for custom title. Old title is '.htmlspecialchars(trim((string) $actor->value('title', '')))." and new title is {$title}.", ['title' => $title]);

        return MybonusRedirect::to($baseUrl, 'title');
    }
}
