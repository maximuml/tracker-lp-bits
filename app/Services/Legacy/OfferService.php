<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\OfferRepository;
use App\Support\Cache;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\SupportContext;
use App\Support\Validators;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Temporary bridge for the legacy offers page.
 */
final class OfferService
{
    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function legacy(Request $request): array|RedirectResponse
    {
        $action = $this->action($request);

        if ($action === 'new_offer') {
            return $this->handleCreate($request);
        }
        if ($action === 'allow_offer') {
            return $this->handleAllow($request);
        }
        if ($action === 'finish_offer') {
            return $this->handleFinish($request);
        }
        if ($action === 'del_offer') {
            return $this->handleDelete($request);
        }
        if ($action === 'take_off_edit') {
            return $this->handleEdit($request);
        }

        $result = $this->renderOffers();
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return ['content' => $result->getContent()];
    }

    private function action(Request $request): string
    {
        foreach (['new_offer', 'allow_offer', 'finish_offer', 'del_offer', 'take_off_edit'] as $key) {
            if ($request->input($key) !== null && $request->input($key) !== '') {
                return $key;
            }
        }

        return '';
    }

    private function lang(string $key): string
    {
        $lang = (array) (SupportContext::getGlobal('lang_offers') ?? []);

        return (string) ($lang[$key] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function curUser(): array
    {
        return (array) (SupportContext::getUser() ?? []);
    }

    private function baseUrl(): string
    {
        return (string) SupportContext::getGlobal('BASEURL', '');
    }

    private function isSecure(): bool
    {
        return (bool) SupportContext::getServerValue('HTTPS');
    }

    private function protocolPrefix(): string
    {
        return $this->isSecure() ? 'https://' : 'http://';
    }

    private function abort(string $heading, string $text, bool $htmlstrip = true): void
    {
        LegacyResponse::abort($heading, $text, $htmlstrip, true, true, false);
    }

    private function handleCreate(Request $request): RedirectResponse
    {
        Permission::assertCan(PermissionEnum::ADD_OFFER);

        if ((int) $request->input('new_offer') !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);
        if (! Validators::isId($userId)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $name = (string) $request->input('name');
        if ($name === '') {
            $this->abort($this->lang('std_error'), $this->lang('std_must_enter_name'));
        }

        $cat = (int) $request->input('type');
        if (! Validators::isId($cat)) {
            $this->abort($this->lang('std_error'), $this->lang('std_must_select_category'));
        }

        $descrmain = (string) Input::unescape($request->input('body'));
        if (! $descrmain) {
            $this->abort($this->lang('std_error'), $this->lang('std_must_enter_description'));
        }

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort($this->lang('std_error'), $this->lang('std_wrong_image_format'));
            }
            $pic = '[img]' . $picture . "[/img]\n";
        }

        $descr = $pic . $descrmain;

        if (OfferRepository::offerNameExists($name)) {
            $this->abort($this->lang('std_error'), $this->lang('std_offer_exists') . '<a class=altlink href=offers.php>' . $this->lang('text_view_all_offers') . '</a>', false);
        }

        $id = OfferRepository::createOffer([
            'userid' => $userId,
            'name' => $name,
            'descr' => $descr,
            'category' => $cat,
            'added' => date('Y-m-d H:i:s'),
            'allowed' => 'pending',
            'yeah' => 0,
            'against' => 0,
            'comments' => 0,
        ]);

        if (! $id) {
            $this->abort($this->lang('std_error'), 'mysql puked');
        }

        OfferRepository::addStaffMessage($userId, (string) ($curuser['username'] ?? ''), $name, $id);
        Cache::clearStaffMessage();
        Log::writeWithContext("offer {$name} was added by " . ($curuser['username'] ?? ''), 'normal');

        return redirect("/offers.php?id={$id}&off_details=1");
    }

    private function handleAllow(Request $request): RedirectResponse
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

        $offer = OfferRepository::findOfferWithUser($offid);
        if (! $offer) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) $arr['userid']);
        $offeruptimeout = (int) (SupportContext::getGlobal('offeruptimeout_main') ?? 0);

        if ($offeruptimeout) {
            $timeouthour = (int) floor($offeruptimeout / 3600);
            $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale) . $timeouthour . Locale::trans('offer.msg_hours_otherwise', [], $locale);
        } else {
            $timeoutnote = '';
        }

        $curuser = $this->curUser();
        $url = $this->protocolPrefix() . $this->baseUrl() . "/offers.php?id={$offid}&off_details=1";
        $msg = ($curuser['username'] ?? '') . Locale::trans('offer.msg_has_allowed', [], $locale) . "[b][url={$url}]" . $arr['name'] . "[/url][/b]. " . Locale::trans('offer.msg_find_offer_option', [], $locale) . $timeoutnote;
        $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
        $allowedtime = date('Y-m-d H:i:s');

        Message::add([
            'sender' => 0,
            'receiver' => (int) $arr['userid'],
            'msg' => $msg,
            'subject' => $subject,
            'added' => $allowedtime,
        ]);

        OfferRepository::allowOffer($offid, $allowedtime);
        Log::writeWithContext(($curuser['username'] ?? '') . " allowed offer {$arr['name']}", 'normal');

        return redirect("/offers.php?id={$offid}&off_details=1");
    }

    private function handleFinish(Request $request): RedirectResponse
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

        $offer = OfferRepository::findOfferWithUser($offid);
        if (! $offer) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
        }

        $arr = $offer->toArray();
        $arr['username'] = $offer->user->username ?? '';
        $locale = Locale::userLocale((int) $arr['userid']);
        $offeruptimeout = (int) (SupportContext::getGlobal('offeruptimeout_main') ?? 0);
        $minoffervotes = (int) (SupportContext::getGlobal('minoffervotes') ?? 0);
        $curuser = $this->curUser();

        $voteCounts = OfferRepository::getVoteCounts($offid);
        $yes = (int) $voteCounts['yeah'];
        $no = (int) $voteCounts['against'];

        if ($yes === 0 && $no === 0) {
            $this->abort($this->lang('std_sorry'), $this->lang('std_no_votes_yet') . "<a href=offers.php?id={$offid}&off_details=1>" . $this->lang('std_back_to_offer_detail') . '</a>', false);
        }

        $finishvotetime = date('Y-m-d H:i:s');
        $url = $this->protocolPrefix() . $this->baseUrl() . "/offers.php?id={$offid}&off_details=1";

        if (($yes - $no) >= $minoffervotes) {
            if ($offeruptimeout) {
                $timeouthour = (int) floor($offeruptimeout / 3600);
                $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale) . $timeouthour . Locale::trans('offer.msg_hours_otherwise', [], $locale);
            } else {
                $timeoutnote = '';
            }

            $msg = Locale::trans('offer.msg_offer_voted_on', [], $locale) . "[b][url={$url}]" . $arr['name'] . "[/url][/b]." . Locale::trans('offer.msg_find_offer_option', [], $locale) . $timeoutnote;
            $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);
            OfferRepository::allowOffer($offid, $finishvotetime);
        } elseif (($no - $yes) >= $minoffervotes) {
            $msg = Locale::trans('offer.msg_offer_voted_off', [], $locale) . "[b][url={$url}]" . $arr['name'] . "[/url][/b]." . Locale::trans('offer.msg_offer_deleted', [], $locale);
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            OfferRepository::denyOffer($offid);
        } else {
            return redirect("/offers.php?id={$offid}&off_details=1");
        }

        Message::add([
            'sender' => 0,
            'subject' => $subject,
            'receiver' => (int) $arr['userid'],
            'added' => $finishvotetime,
            'msg' => $msg,
        ]);

        $curuser = $this->curUser();
        Log::writeWithContext(($curuser['username'] ?? '') . " closed poll {$arr['name']}", 'normal');

        return redirect("/offers.php?id={$offid}&off_details=1");
    }

    private function handleDelete(Request $request): RedirectResponse
    {
        if ((int) $request->input('del_offer') !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offerId = (int) $request->input('id');
        if (! Validators::isId($offerId)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offerRecord = OfferRepository::findOffer($offerId);
        if (! $offerRecord) {
            $this->abort($this->lang('std_error'), $this->lang('text_nothing_found'));
        }

        $num = $offerRecord->toArray();
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($userId !== (int) $num['userid'] && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort($this->lang('std_error'), $this->lang('std_cannot_delete_others_offer'));
        }

        $sure = (int) $request->input('sure');
        if ($sure !== 0 && $sure !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        if ($sure === 0) {
            $this->abort($this->lang('std_delete_offer'), $this->lang('std_delete_offer_note') . "<br /><form method=post action=offers.php?id={$offerId}&del_offer=1&sure=1>" . $this->lang('text_reason_is') . "<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"" . $this->lang('submit_confirm') . "\"></form>", false);
        }

        $reason = (string) $request->input('reason');
        OfferRepository::deleteOffer($offerId);
        OfferRepository::deleteOfferVotes($offerId);
        OfferRepository::deleteOfferComments($offerId);

        if ($userId !== (int) $num['userid']) {
            $locale = Locale::userLocale((int) $num['userid']);
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            $msg = Locale::trans('offer.msg_your_offer', [], $locale) . $num['name'] . Locale::trans('offer.msg_was_deleted_by', [], $locale) . "[url=userdetails.php?id={$userId}]" . ($curuser['username'] ?? '') . "[/url]" . Locale::trans('offer.msg_blank', [], $locale) . ($reason !== '' ? Locale::trans('offer.msg_reason_is', [], $locale) . $reason : '');

            Message::add([
                'sender' => 0,
                'receiver' => (int) $num['userid'],
                'msg' => $msg,
                'subject' => $subject,
                'added' => now(),
            ]);
        }

        Log::writeWithContext("Offer: {$offerId} ({$num['name']}) was deleted by " . ($curuser['username'] ?? '') . ($reason !== '' ? " ({$reason})" : ''), 'normal');

        return redirect('/offers.php');
    }

    private function handleEdit(Request $request): RedirectResponse
    {
        if ((int) $request->input('take_off_edit') !== 1) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $id = (int) $request->input('id');
        if (! Validators::isId($id)) {
            $this->abort($this->lang('std_error'), $this->lang('std_smell_rat'));
        }

        $offerOwner = OfferRepository::getOfferOwner($id);
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($offerOwner !== $userId && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort($this->lang('std_error'), $this->lang('std_access_denied'));
        }

        $name = (string) $request->input('name');

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort($this->lang('std_error'), $this->lang('std_wrong_image_format'));
            }
            $pic = '[img]' . $picture . "[/img]\n";
        }

        $descr = $pic . (string) Input::unescape($request->input('body'));
        if ($name === '') {
            $this->abort($this->lang('std_error'), $this->lang('std_must_enter_name'));
        }
        if ($descr === '') {
            $this->abort($this->lang('std_error'), $this->lang('std_must_enter_description'));
        }

        $cat = (int) $request->input('category');
        if (! Validators::isId($cat)) {
            $this->abort($this->lang('std_error'), $this->lang('std_must_select_category'));
        }

        OfferRepository::updateOffer($id, [
            'category' => $cat,
            'name' => $name,
            'descr' => $descr,
        ]);

        return redirect("/offers.php?id={$id}&off_details=1");
    }

    private function renderOffers(): Response|RedirectResponse
    {
        $path = __DIR__ . '/offers_content.php';

        if (! file_exists($path)) {
            return response('Legacy content missing: offers', 500);
        }

        ob_start();
        try {
            extract(SupportContext::getGlobalsForView());
            include $path;
        } catch (HttpResponseException $e) {
            ob_get_clean();

            throw $e;
        }

        $content = (string) ob_get_clean();

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url);
            }
        }

        return response($content);
    }
}
