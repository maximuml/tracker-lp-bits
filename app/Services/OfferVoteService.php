<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\OfferRepository;
use App\Repositories\OfferVoteRepository;
use App\Support\Bonus;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
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
        private readonly Globals $globals,
        private readonly OfferRepository $offerRepository,
        private readonly OfferVoteRepository $offerVoteRepository,
    ) {}

    public function handleVote(Request $request): ?Response
    {
        $vote = (string) $request->query('vote', '');
        if ($vote === '') {
            return null;
        }

        if ($vote === 'against' && ! Permission::can(PermissionEnum::AGAINST_OFFER)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }
        if ($vote !== 'yeah' && $vote !== 'against') {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offerid = (int) $request->query('id', 0);
        $curuser = $this->curUser();
        $userid = (int) ($curuser['id'] ?? 0);

        if ($this->offerRepository->getOfferOwner($offerid) === $userid) {
            $this->abort($this->lang('std_error'), $this->lang('std_cannot_vote_youself'));
        }
        if ($this->offerVoteRepository->userVoted($offerid, $userid)) {
            $this->abort($this->lang('std_already_voted'), $this->lang('std_already_voted_note')."<a  href=offers.php?id={$offerid}&off_details=1>".$this->lang('std_back_to_offer_detail'), false);
        }

        $offer = $this->offerRepository->findOfferWithUser($offerid);
        if ($offer === null) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
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
        $minoffervotes = (int) ($this->globals->get('minoffervotes') ?? 0);
        $offeruptimeout = (int) ($this->globals->get('offeruptimeout_main') ?? 0);
        $offervoteBonus = (float) ($this->globals->get('offervote_bonus') ?? 0);
        $finishtime = date('Y-m-d H:i:s');
        $url = $this->protocolPrefix().$this->baseUrl()."/offers.php?id={$offerid}&off_details=1";

        if (($yeah - $against) >= $minoffervotes && $offerVotes->allowed !== OfferAllowed::ALLOWED) {
            if ($offeruptimeout) {
                $timeouthour = (int) floor($offeruptimeout / 3600);
                $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
            } else {
                $timeoutnote = '';
            }
            $this->offerRepository->allowOffer($offerid, $finishtime);
            Message::add([
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
            Message::add([
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
            '<h1 align=center>'.$this->lang('std_vote_accepted').'</h1>'
            .$this->lang('std_vote_accepted_note')
            ."<a  href=offers.php?id={$offerid}&off_details=1>".$this->lang('std_back_to_offer_detail').'</a>'
        );
    }

    private function lang(string $key): string
    {
        $lang = (array) ($this->globals->get('lang_offers') ?? []);

        return (string) ($lang[$key] ?? '');
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
        return (string) $this->globals->get('BASEURL', '');
    }

    private function isSecure(): bool
    {
        return (bool) Input::serverValue('HTTPS');
    }

    private function protocolPrefix(): string
    {
        return $this->isSecure() ? 'https://' : 'http://';
    }

    private function abort(string $heading, string $text, bool $htmlstrip = true): void
    {
        LegacyResponse::abort($heading, $text, $htmlstrip);
    }
}
