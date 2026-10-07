<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\MessageRepository;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\RequestValues;
use App\Support\Url;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * Staff offer-resolution handlers (allow_offer, finish_offer).
 * Extracted from OfferService to keep both classes under the
 * 400-line ratchet.
 */
final class OfferModerationService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function curUser(): array
    {
        return (array) ($this->currentUser->get() ?? []);
    }

    private function baseUrl(): string
    {
        return SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost');
    }

    private function abort(string $heading, string $text, bool $htmlstrip = true): void
    {
        LegacyResponse::abort($heading, $text, $htmlstrip);
    }

    public function handleAllow(Request $request): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('offers.std_access_denied'), __('offers.std_mans_job'));
        }

        $offid = (int) $request->input('offerid');
        if (! Validators::isId($offid)) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $offer = $this->offerRepository->findOfferWithUser($offid);
        if (! $offer) {
            $this->abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        if ($offer === null) {
            throw new LogicException('Expected non-null offer.');
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) ($arr['userid'] ?? 0));
        $offeruptimeout = SiteConfig::current()->main->offerUploadTimeout(0);

        if ($offeruptimeout) {
            $timeouthour = (int) floor($offeruptimeout / 3600);
            $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
        } else {
            $timeoutnote = '';
        }

        $curuser = $this->curUser();
        $url = Url::absolute($this->baseUrl())."/web/offers?id={$offid}&off_details=1";
        $msg = ($curuser['username'] ?? '').Locale::trans('offer.msg_has_allowed', [], $locale)."[b][url={$url}]".($arr['name'] ?? '').'[/url][/b]. '.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
        $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
        $allowedtime = date('Y-m-d H:i:s');

        $this->messageRepository->add([
            'sender' => null,
            'receiver' => (int) ($arr['userid'] ?? 0),
            'msg' => $msg,
            'subject' => $subject,
            'added' => $allowedtime,
        ]);

        $this->offerRepository->allowOffer($offid, $allowedtime);
        Log::writeWithContext(($curuser['username'] ?? '').' allowed offer '.($arr['name'] ?? ''), 'normal');

        return redirect("/web/offers?id={$offid}&off_details=1");
    }

    public function handleFinish(Request $request): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('offers.std_access_denied'), __('offers.std_have_no_permission'));
        }

        $offid = (int) $request->input('finish');
        if (! Validators::isId($offid)) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $offer = $this->offerRepository->findOfferWithUser($offid);
        if (! $offer) {
            $this->abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        if ($offer === null) {
            throw new LogicException('Expected non-null offer.');
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) ($arr['userid'] ?? 0));
        $offeruptimeout = SiteConfig::current()->main->offerUploadTimeout(0);
        $minoffervotes = SiteConfig::current()->main->minOfferVotes();
        $curuser = $this->curUser();

        $voteCounts = $this->offerVoteRepository->getVoteCounts($offid);
        $yes = (int) $voteCounts['yeah'];
        $no = (int) $voteCounts['against'];

        if ($yes === 0 && $no === 0) {
            $this->abort(__('offers.std_sorry'), __('offers.std_no_votes_yet').view('offers._details_link', ['url' => "/web/offers?id={$offid}&off_details=1"])->render(), false);
        }

        $finishvotetime = date('Y-m-d H:i:s');
        $url = Url::absolute($this->baseUrl())."/web/offers?id={$offid}&off_details=1";

        if (($yes - $no) >= $minoffervotes) {
            if ($offeruptimeout) {
                $timeouthour = (int) floor($offeruptimeout / 3600);
                $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
            } else {
                $timeoutnote = '';
            }

            $msg = Locale::trans('offer.msg_offer_voted_on', [], $locale)."[b][url={$url}]".($arr['name'] ?? '').'[/url][/b].'.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
            $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
            $this->offerRepository->allowOffer($offid, $finishvotetime);
        } elseif (($no - $yes) >= $minoffervotes) {
            $msg = Locale::trans('offer.msg_offer_voted_off', [], $locale)."[b][url={$url}]".($arr['name'] ?? '').'[/url][/b].'.Locale::trans('offer.msg_offer_deleted', [], $locale);
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            $this->offerRepository->denyOffer($offid);
        } else {
            return redirect("/web/offers?id={$offid}&off_details=1");
        }

        $this->messageRepository->add([
            'sender' => null,
            'subject' => $subject,
            'receiver' => (int) ($arr['userid'] ?? 0),
            'added' => $finishvotetime,
            'msg' => $msg,
        ]);

        $curuser = $this->curUser();
        Log::writeWithContext(($curuser['username'] ?? '').' closed poll '.($arr['name'] ?? ''), 'normal');

        return redirect("/web/offers?id={$offid}&off_details=1");
    }
}
