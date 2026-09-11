<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\ViewModels\OfferPageViewModel;
use Illuminate\Http\Request;

final class OfferPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly OfferPageListBuilder $listBuilder,
        private readonly OfferPageAddBuilder $addBuilder,
        private readonly OfferPageDetailsBuilder $detailsBuilder,
        private readonly OfferPageEditBuilder $editBuilder,
        private readonly OfferPageVoteListBuilder $voteListBuilder,
    ) {}

    public function build(Request $request): OfferPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $lang = (array) ($this->globals->get('lang_offers') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $action = $this->resolveAction($request);

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => (string) $this->globals->get('BASEURL', ''),
            'contentWidth' => (string) $this->globals->get('CONTENT_WIDTH', '737'),
            'browsecatmode' => $this->globals->get('browsecatmode', 1),
            'enableoffer' => (string) $this->globals->get('enableoffer', 'yes'),
            'minoffervotes' => (int) $this->globals->get('minoffervotes', 0),
            'offervotetimeoutMain' => (int) $this->globals->get('offervotetimeout_main', 0),
            'offeruptimeoutMain' => (int) $this->globals->get('offeruptimeout_main', 0),
            'offervoteBonus' => (float) $this->globals->get('offervote_bonus', 0),
            'uploadClass' => (int) $this->globals->get('upload_class', 0),
            'addofferClass' => (int) $this->globals->get('addoffer_class', 0),
            'againstofferClass' => (int) $this->globals->get('againstoffer_class', 0),
        ];

        if ($data['enableoffer'] === 'no') {
            LegacyResponse::permissionDenied();
        }

        switch ($action) {
            case 'add_offer':
                Permission::assertCan(PermissionEnum::ADD_OFFER);
                $data['add_offer'] = $this->addBuilder->build($lang, $data['browsecatmode']);
                break;
            case 'off_details':
                $data['off_details'] = $this->detailsBuilder->build($lang, $curUser, $userId, $request);
                break;
            case 'edit_offer':
                $data['edit_offer'] = $this->editBuilder->build($lang, $curUser, $userId, $request, $data['browsecatmode']);
                break;
            case 'offer_vote':
                $data['offer_vote'] = $this->voteListBuilder->build($lang, $request);
                break;
            default:
                $data['list'] = $this->listBuilder->build($lang, $curUser, $userId, $request, $data);
                $data['action'] = 'list';
                break;
        }

        return new OfferPageViewModel(
            lang: $data['lang'],
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
