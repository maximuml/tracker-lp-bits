<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OfferRepository;
use App\Support\Input;
use App\Support\Pagination;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

final class OfferPageVoteListBuilder
{
    public function __construct(
        private readonly OfferRepository $offerRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function build(array $lang, Request $request): array
    {
        $offerId = (int) $request->query('id', 0);
        $count = $this->offerRepository->getVoteCount($offerId);
        $offerName = (string) $this->offerRepository->getOfferName($offerId);

        $perpage = 25;
        $self = Input::serverValue('PHP_SELF');
        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $self.'?id='.$offerId.'&offer_vote=1&');
        $voteRows = $this->offerRepository->getVoteRows($offerId, (int) $offset, (int) $perpage);

        $rows = [];
        foreach ($voteRows as $arr) {
            $arrArr = (array) $arr;
            $vote = match ($arrArr['vote'] ?? '') {
                'yeah' => '<b><font color=green>'.htmlspecialchars((string) ($lang['text_for'] ?? '')).'</font></b>',
                'against' => '<b><font color=red>'.htmlspecialchars((string) ($lang['text_against'] ?? '')).'</font></b>',
                default => 'unknown',
            };
            $rows[] = [
                'username' => UserDisplay::username((int) ($arrArr['userid'] ?? 0)),
                'vote' => $vote,
            ];
        }

        return [
            'offerId' => $offerId,
            'offerName' => htmlspecialchars($offerName),
            'hasVotes' => ! $voteRows->isEmpty(),
            'noVotesNote' => (string) ($lang['std_no_votes_yet'] ?? ''),
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'rows' => $rows,
        ];
    }
}
