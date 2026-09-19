<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\ViewModels\Offer\OfferDetailsViewModel;
use App\ViewModels\Offer\OfferListViewModel;

/**
 * ViewModel for the offers page.
 *
 * Returned by OfferPageService::build().
 */
final class OfferPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $add_offer
     * @param  array<string, mixed>|null  $edit_offer
     * @param  array<string, mixed>|null  $offer_vote
     */
    public function __construct(
        public readonly array $curUser,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $baseUrl,
        public readonly string $contentWidth,
        public readonly mixed $browsecatmode,
        public readonly string $enableoffer,
        public readonly int $minoffervotes,
        public readonly int $offervotetimeoutMain,
        public readonly int $offeruptimeoutMain,
        public readonly float $offervoteBonus,
        public readonly int $uploadClass,
        public readonly int $addofferClass,
        public readonly int $againstofferClass,
        public readonly ?array $add_offer = null,
        public readonly ?OfferDetailsViewModel $off_details = null,
        public readonly ?array $edit_offer = null,
        public readonly ?array $offer_vote = null,
        public readonly ?OfferListViewModel $list = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'curUser' => $this->curUser,
            'userId' => $this->userId,
            'action' => $this->action,
            'baseUrl' => $this->baseUrl,
            'contentWidth' => $this->contentWidth,
            'browsecatmode' => $this->browsecatmode,
            'enableoffer' => $this->enableoffer,
            'minoffervotes' => $this->minoffervotes,
            'offervotetimeoutMain' => $this->offervotetimeoutMain,
            'offeruptimeoutMain' => $this->offeruptimeoutMain,
            'offervoteBonus' => $this->offervoteBonus,
            'uploadClass' => $this->uploadClass,
            'addofferClass' => $this->addofferClass,
            'againstofferClass' => $this->againstofferClass,
            'add_offer' => $this->add_offer,
            'off_details' => $this->off_details,
            'edit_offer' => $this->edit_offer,
            'offer_vote' => $this->offer_vote,
            'list' => $this->list,
        ];
    }
}
