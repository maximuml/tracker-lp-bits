<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Enums\OfferVote;
use App\Support\Pagination;
use App\Support\RequestValues;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

/**
 * Builds the offer vote listing (who voted what on one offer).
 */
final class OfferVoteListBuilder
{
    public function __construct(
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $offerId = (int) $request->query('id', 0);
        $count = $this->offerVoteRepository->getVoteCount($offerId);
        $offerName = (string) $this->offerRepository->getOfferName($offerId);

        $perpage = 25;
        $self = RequestValues::serverValue('PHP_SELF');
        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $self.'?id='.$offerId.'&offer_vote=1&');
        $voteRows = $this->offerVoteRepository->getVoteRows($offerId, (int) $offset, (int) $perpage);

        UserDisplay::preload($voteRows->map(fn ($r) => (int) (((array) $r)['userid'] ?? 0))->all());
        $rows = [];
        foreach ($voteRows as $arr) {
            $arrArr = (array) $arr;
            $rows[] = [
                'username' => UserDisplay::username((int) ($arrArr['userid'] ?? 0)),
                'vote' => OfferVote::tryFrom((int) ($arrArr['vote'] ?? -1))?->stringValue() ?? 'unknown',
            ];
        }

        return [
            'offerId' => $offerId,
            'offerName' => htmlspecialchars($offerName),
            'hasVotes' => ! $voteRows->isEmpty(),
            'noVotesNote' => __('offers.std_no_votes_yet'),
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'rows' => $rows,
        ];
    }
}
