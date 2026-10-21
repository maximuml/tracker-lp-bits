<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Services\OfferVoteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Offer-details vote row — yeah/against buttons + live counts morph in
 * place through the same OfferVoteService::castVote() guards and
 * mutation the legacy POST /web/offers/vote handler uses. Guard
 * failures surface inline instead of a full-page abort.
 */
final class OfferVote extends Component
{
    #[Locked]
    public int $offerId = 0;

    #[Locked]
    public bool $canAgainst = false;

    public int $yeah = 0;

    public int $against = 0;

    public bool $voted = false;

    public string $error = '';

    private ?OfferVoteService $offerVoteService = null;

    private ?OfferRepositoryInterface $offerRepository = null;

    private ?OfferVoteRepositoryInterface $offerVoteRepository = null;

    private ?ActorContext $actor = null;

    public function boot(
        OfferVoteService $offerVoteService,
        OfferRepositoryInterface $offerRepository,
        OfferVoteRepositoryInterface $offerVoteRepository,
        ActorContext $actor,
    ): void {
        $this->offerVoteService = $offerVoteService;
        $this->offerRepository = $offerRepository;
        $this->offerVoteRepository = $offerVoteRepository;
        $this->actor = $actor;
    }

    public function vote(string $vote): void
    {
        if (! in_array($vote, ['yeah', 'against'], true)) {
            return;
        }
        if (! $this->actor()->isAuthenticated()) {
            $this->error = __('offers.std_error');

            return;
        }
        $uid = $this->actor()->id;

        if ($vote === 'against' && ! $this->actor()->can(PermissionEnum::AGAINST_OFFER)) {
            $this->error = __('offers.std_smell_rat');

            return;
        }
        if ($this->offerRepository()->getOfferOwner($this->offerId) === $uid) {
            $this->error = __('offers.std_cannot_vote_youself');

            return;
        }
        if ($this->offerVoteRepository()->userVoted($this->offerId, $uid)) {
            $this->error = __('offers.std_already_voted');

            return;
        }

        try {
            $this->offerVoteService()->castVote($this->offerId, $vote);
        } catch (HttpResponseException) {
            $this->error = __('offers.std_error');

            return;
        }

        $counts = $this->offerVoteRepository()->getVoteCounts($this->offerId);
        $this->yeah = (int) $counts['yeah'];
        $this->against = (int) $counts['against'];
        $this->voted = true;
        $this->error = '';
    }

    public function render(): View
    {
        $alreadyVoted = $this->voted;
        if (! $alreadyVoted) {
            $uid = $this->actor()->id;
            $alreadyVoted = $uid > 0 && $this->offerVoteRepository()->userVoted($this->offerId, $uid);
        }

        return view('livewire.offer-vote', ['alreadyVoted' => $alreadyVoted]);
    }

    private function offerVoteService(): OfferVoteService
    {
        return $this->offerVoteService ?? throw new \LogicException('OfferVote used before boot()');
    }

    private function offerRepository(): OfferRepositoryInterface
    {
        return $this->offerRepository ?? throw new \LogicException('OfferVote used before boot()');
    }

    private function offerVoteRepository(): OfferVoteRepositoryInterface
    {
        return $this->offerVoteRepository ?? throw new \LogicException('OfferVote used before boot()');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('OfferVote used before boot()');
    }
}
