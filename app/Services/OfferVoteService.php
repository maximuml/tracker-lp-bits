<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\MessageRepository;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;

/**
 * Handles offer voting (?id=N&vote=yeah|against) — ported from the legacy
 * offers.php flow: guards (against-permission, owner, duplicate), vote
 * increment, allow/deny thresholds with PM notification, vote record and
 * bonus points.
 */
final class OfferVoteService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function handleVote(Request $request): ?Response
    {
        $vote = (string) $request->query('vote', '');
        if ($vote === '') {
            return null;
        }

        if ($vote === 'against' && ! Permission::can(PermissionEnum::AGAINST_OFFER)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }
        if ($vote !== 'yeah' && $vote !== 'against') {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $offerid = (int) $request->query('id', 0);
        $curuser = $this->curUser();
        $userid = (int) ($curuser['id'] ?? 0);

        if ($this->offerRepository->getOfferOwner($offerid) === $userid) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_cannot_vote_youself'));
        }
        if ($this->offerVoteRepository->userVoted($offerid, $userid)) {
            $this->abort(__('legacy/offers.std_already_voted'), view('offers._back_to_details', ['note' => __('legacy/offers.std_already_voted_note'), 'url' => "offers.php?id={$offerid}&off_details=1"])->render(), false);
        }

        $offer = $this->offerRepository->findOfferWithUser($offerid);
        if ($offer === null) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.text_nothing_found'));
            throw new LogicException('Expected non-null offer.');
        }

        $this->offerVoteRepository->incrementVote($offerid, $vote);
        $locale = Locale::userLocale((int) $offer->userid);

        $offerVotes = $this->offerRepository->findOfferWithVotes($offerid);
        if ($offerVotes === null) {
            throw new LogicException('Expected non-null offer.');
        }
        $yeah = (int) $offerVotes->yeah;
        $against = (int) $offerVotes->against;
        $minoffervotes = SiteConfig::current()->main->minOfferVotes();
        $offeruptimeout = SiteConfig::current()->main->offerUploadTimeout(0);
        $offervoteBonus = SiteConfig::current()->bonus->offerVote();
        $finishtime = date('Y-m-d H:i:s');
        $url = Url::absolute($this->baseUrl())."/offers.php?id={$offerid}&off_details=1";

        if (($yeah - $against) >= $minoffervotes && $offerVotes->allowed !== OfferAllowed::ALLOWED) {
            if ($offeruptimeout) {
                $timeouthour = (int) floor($offeruptimeout / 3600);
                $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
            } else {
                $timeoutnote = '';
            }
            $this->offerRepository->allowOffer($offerid, $finishtime);
            $this->messageRepository->add([
                'sender' => null,
                'subject' => Locale::trans('offer.msg_your_offer_allowed', [], $locale),
                'receiver' => (int) $offerVotes->userid,
                'added' => $finishtime,
                'msg' => Locale::trans('offer.msg_offer_voted_on', [], $locale)."[b][url={$url}]".$offerVotes->name.'[/url][/b].'.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote,
            ]);
            Log::writeWithContext("System allowed offer {$offerVotes->name}", 'normal');
        }
        if (($against - $yeah) >= $minoffervotes && $offerVotes->allowed !== OfferAllowed::DENIED) {
            $this->offerRepository->denyOffer($offerid);
            $this->messageRepository->add([
                'sender' => null,
                'subject' => Locale::trans('offer.msg_offer_deleted', [], $locale),
                'receiver' => (int) $offerVotes->userid,
                'added' => $finishtime,
                'msg' => Locale::trans('offer.msg_offer_voted_off', [], $locale)."[b][url={$url}]".$offerVotes->name.'[/url][/b].'.Locale::trans('offer.msg_offer_deleted', [], $locale),
            ]);
            Log::writeWithContext("System denied offer {$offerVotes->name}", 'normal');
        }

        $this->offerVoteRepository->recordVote($offerid, $userid, $vote);
        Bonus::updatePoints('+', $offervoteBonus, $userid);

        return response(
            view('offers.vote-accepted', ['url' => "offers.php?id={$offerid}&off_details=1"])->render()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function curUser(): array
    {
        return (array) ($this->currentUser->get() ?? []);
    }

    private function baseUrl(): string
    {
        return SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost');
    }

    private function abort(string $heading, string $text, bool $htmlstrip = true): void
    {
        LegacyResponse::abort($heading, $text, $htmlstrip);
    }
}
