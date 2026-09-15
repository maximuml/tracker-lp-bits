<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\OfferRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
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
        private readonly Globals $globals,
        private readonly OfferRepository $offerRepository,
    ) {}

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

    public function handleAllow(Request $request): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort($this->lang('std_access_denied'), $this->lang('std_mans_job'));
        }

        if ((int) $request->input('allow_offer') !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offid = (int) $request->input('offerid');
        if (! Validators::isId($offid)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offer = $this->offerRepository->findOfferWithUser($offid);
        if (! $offer) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
        }
        if ($offer === null) {
            throw new LogicException('Expected non-null offer.');
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) $arr['userid']);
        $offeruptimeout = (int) ($this->globals->get('offeruptimeout_main') ?? 0);

        if ($offeruptimeout) {
            $timeouthour = (int) floor($offeruptimeout / 3600);
            $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
        } else {
            $timeoutnote = '';
        }

        $curuser = $this->curUser();
        $url = $this->protocolPrefix().$this->baseUrl()."/offers.php?id={$offid}&off_details=1";
        $msg = ($curuser['username'] ?? '').Locale::trans('offer.msg_has_allowed', [], $locale)."[b][url={$url}]".$arr['name'].'[/url][/b]. '.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
        $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
        $allowedtime = date('Y-m-d H:i:s');

        Message::add([
            'sender' => null,
            'receiver' => (int) $arr['userid'],
            'msg' => $msg,
            'subject' => $subject,
            'added' => $allowedtime,
        ]);

        $this->offerRepository->allowOffer($offid, $allowedtime);
        Log::writeWithContext(($curuser['username'] ?? '')." allowed offer {$arr['name']}", 'normal');

        return redirect("/offers.php?id={$offid}&off_details=1");
    }

    public function handleFinish(Request $request): RedirectResponse
    {
        if (! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort($this->lang('std_access_denied'), $this->lang('std_have_no_permission'));
        }

        if ((int) $request->input('finish_offer') !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offid = (int) $request->input('finish');
        if (! Validators::isId($offid)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offer = $this->offerRepository->findOfferWithUser($offid);
        if (! $offer) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
        }
        if ($offer === null) {
            throw new LogicException('Expected non-null offer.');
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) $arr['userid']);
        $offeruptimeout = (int) ($this->globals->get('offeruptimeout_main') ?? 0);
        $minoffervotes = (int) ($this->globals->get('minoffervotes') ?? 0);
        $curuser = $this->curUser();

        $voteCounts = $this->offerRepository->getVoteCounts($offid);
        $yes = (int) $voteCounts['yeah'];
        $no = (int) $voteCounts['against'];

        if ($yes === 0 && $no === 0) {
            $this->abort($this->lang('std_sorry'), $this->lang('std_no_votes_yet')."<a href=offers.php?id={$offid}&off_details=1>".$this->lang('std_back_to_offer_detail').'</a>', false);
        }

        $finishvotetime = date('Y-m-d H:i:s');
        $url = $this->protocolPrefix().$this->baseUrl()."/offers.php?id={$offid}&off_details=1";

        if (($yes - $no) >= $minoffervotes) {
            if ($offeruptimeout) {
                $timeouthour = (int) floor($offeruptimeout / 3600);
                $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
            } else {
                $timeoutnote = '';
            }

            $msg = Locale::trans('offer.msg_offer_voted_on', [], $locale)."[b][url={$url}]".$arr['name'].'[/url][/b].'.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
            $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
            $this->offerRepository->allowOffer($offid, $finishvotetime);
        } elseif (($no - $yes) >= $minoffervotes) {
            $msg = Locale::trans('offer.msg_offer_voted_off', [], $locale)."[b][url={$url}]".$arr['name'].'[/url][/b].'.Locale::trans('offer.msg_offer_deleted', [], $locale);
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            $this->offerRepository->denyOffer($offid);
        } else {
            return redirect("/offers.php?id={$offid}&off_details=1");
        }

        Message::add([
            'sender' => null,
            'subject' => $subject,
            'receiver' => (int) $arr['userid'],
            'added' => $finishvotetime,
            'msg' => $msg,
        ]);

        $curuser = $this->curUser();
        Log::writeWithContext(($curuser['username'] ?? '')." closed poll {$arr['name']}", 'normal');

        return redirect("/offers.php?id={$offid}&off_details=1");
    }
}
