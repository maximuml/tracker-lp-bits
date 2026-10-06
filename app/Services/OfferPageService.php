<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Services\Offer\OfferAddBuilder;
use App\Services\Offer\OfferDetailsBuilder;
use App\Services\Offer\OfferEditBuilder;
use App\Services\Offer\OfferListBuilder;
use App\Services\Offer\OfferVoteListBuilder;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\ViewModels\OfferPageViewModel;
use Illuminate\Http\Request;

/**
 * Dispatcher for the offers page modes — resolves the requested action and
 * assembles OfferPageViewModel. The per-mode builders live in
 * App\Services\Offer\*Builder.
 */
final class OfferPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly OfferAddBuilder $addBuilder,
        private readonly OfferDetailsBuilder $detailsBuilder,
        private readonly OfferEditBuilder $editBuilder,
        private readonly OfferListBuilder $listBuilder,
        private readonly OfferVoteListBuilder $voteListBuilder,
    ) {}

    public function build(Request $request): OfferPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($this->currentUser->id());

        $action = $this->resolveAction($request);

        $data = [
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost'),
            'contentWidth' => '737',
            'browsecatmode' => SiteConfig::current()->main->browseCat(1),
            'enableoffer' => SiteConfig::current()->main->showOffer(true) ? 'yes' : 'no',
            'minoffervotes' => SiteConfig::current()->main->minOfferVotes(),
            'offervotetimeoutMain' => SiteConfig::current()->main->offerVoteTimeout(0),
            'offeruptimeoutMain' => SiteConfig::current()->main->offerUploadTimeout(0),
            'offervoteBonus' => SiteConfig::current()->bonus->offerVote(),
            'uploadClass' => (int) SiteConfig::current()->authority->permission('upload', 0),
            'addofferClass' => (int) SiteConfig::current()->authority->permission('addoffer', 0),
            'againstofferClass' => (int) SiteConfig::current()->authority->permission('againstoffer', 0),
        ];

        if ($data['enableoffer'] === 'no') {
            LegacyResponse::permissionDenied();
        }

        switch ($action) {
            case 'add_offer':
                Permission::assertCan(PermissionEnum::ADD_OFFER);
                $data['add_offer'] = $this->addBuilder->build($data['browsecatmode']);
                break;
            case 'off_details':
                $data['off_details'] = $this->detailsBuilder->build($curUser, $userId, $request);
                break;
            case 'edit_offer':
                $data['edit_offer'] = $this->editBuilder->build($curUser, $userId, $request, $data['browsecatmode']);
                break;
            case 'offer_vote':
                $data['offer_vote'] = $this->voteListBuilder->build($request);
                break;
            default:
                $data['list'] = $this->listBuilder->build($curUser, $userId, $request, $data);
                $data['action'] = 'list';
                break;
        }

        return new OfferPageViewModel(
            curUser: $data['curUser'],
            userId: $data['userId'],
            action: $data['action'],
            baseUrl: $data['baseUrl'],
            contentWidth: $data['contentWidth'],
            browsecatmode: $data['browsecatmode'],
            enableoffer: $data['enableoffer'],
            minoffervotes: $data['minoffervotes'],
            offervotetimeoutMain: $data['offervotetimeoutMain'],
            offeruptimeoutMain: $data['offeruptimeoutMain'],
            offervoteBonus: $data['offervoteBonus'],
            uploadClass: $data['uploadClass'],
            addofferClass: $data['addofferClass'],
            againstofferClass: $data['againstofferClass'],
            add_offer: $data['add_offer'] ?? null,
            off_details: $data['off_details'] ?? null,
            edit_offer: $data['edit_offer'] ?? null,
            offer_vote: $data['offer_vote'] ?? null,
            list: $data['list'] ?? null,
        );
    }

    private function resolveAction(Request $request): string
    {
        foreach (['add_offer', 'off_details', 'edit_offer', 'offer_vote'] as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '' && $value !== '0') {
                return $key;
            }
        }

        return 'list';
    }
}
