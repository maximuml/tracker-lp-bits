<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\OfferCommentRepository;
use App\Repositories\OfferRepository;
use App\Repositories\OfferVoteRepository;
use App\Support\Cache;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

/**
 * Handles offer action mutations (create, delete, edit).
 * Staff resolution actions (allow, finish) are in OfferModerationService.
 * Page rendering is handled by OfferPageService.
 */
final class OfferService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly OfferRepository $offerRepository,
        private readonly OfferVoteRepository $offerVoteRepository,
        private readonly OfferCommentRepository $offerCommentRepository,
        private readonly OfferModerationService $offerModerationService,
    ) {}

    public function handleActionPublic(Request $request): ?RedirectResponse
    {
        $action = $this->action($request);

        if ($action === '') {
            return null;
        }

        if (! $request->isMethod('post')) {
            return redirect('/offers.php');
        }

        if ($action === 'new_offer') {
            return $this->handleCreate($request);
        }
        if ($action === 'allow_offer') {
            return $this->offerModerationService->handleAllow($request);
        }
        if ($action === 'finish_offer') {
            return $this->offerModerationService->handleFinish($request);
        }
        if ($action === 'del_offer') {
            return $this->handleDelete($request);
        }
        if ($action === 'take_off_edit') {
            return $this->handleEdit($request);
        }

        return null;
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

    /**
     * @return array<string, mixed>
     */
    private function curUser(): array
    {
        return (array) ($this->currentUser->get() ?? []);
    }

    private function abort(string $heading, string $text, bool $htmlstrip = true): void
    {
        LegacyResponse::abort($heading, $text, $htmlstrip);
    }

    private function handleCreate(Request $request): RedirectResponse
    {
        Permission::assertCan(PermissionEnum::ADD_OFFER);

        if ((int) $request->input('new_offer') !== 1) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);
        if (! Validators::isId($userId)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $name = (string) $request->input('name');
        if ($name === '') {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_enter_name'));
        }

        $cat = (int) ($request->input('type') ?? $request->input('category'));
        if (! Validators::isId($cat)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_select_category'));
        }

        $descrmain = (string) Input::unescape($request->input('body') ?? $request->input('descr'));
        if (! $descrmain) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_enter_description'));
        }

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_wrong_image_format'));
            }
            $pic = '[img]'.$picture."[/img]\n";
        }

        $descr = $pic.$descrmain;

        if ($this->offerRepository->offerNameExists($name)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_offer_exists').'<a class=altlink href=offers.php>'.__('legacy/offers.text_view_all_offers').'</a>', false);
        }

        $id = $this->offerRepository->createOffer([
            'userid' => $userId,
            'name' => $name,
            'descr' => $descr,
            'category' => $cat,
            'added' => date('Y-m-d H:i:s'),
            'allowed' => OfferAllowed::PENDING->value,
            'yeah' => 0,
            'against' => 0,
            'comments' => 0,
        ]);

        if (! $id) {
            $this->abort(__('legacy/offers.std_error'), 'mysql puked');
        }

        $this->offerRepository->addStaffMessage($userId, (string) ($curuser['username'] ?? ''), $name, $id);
        Cache::clearStaffMessage();
        Log::writeWithContext("offer {$name} was added by ".($curuser['username'] ?? ''), 'normal');

        return redirect("/offers.php?id={$id}&off_details=1");
    }

    private function handleDelete(Request $request): RedirectResponse
    {
        if ((int) $request->input('del_offer') !== 1) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $offerId = (int) $request->input('id');
        if (! Validators::isId($offerId)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $offerRecord = $this->offerRepository->findOffer($offerId);
        if (! $offerRecord) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.text_nothing_found'));
        }
        if ($offerRecord === null) {
            throw new LogicException('Expected non-null offer record.');
        }

        $num = $offerRecord->toArray();
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($userId !== (int) $num['userid'] && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_cannot_delete_others_offer'));
        }

        $sure = (int) $request->input('sure');
        if ($sure !== 0 && $sure !== 1) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        if ($sure === 0) {
            $this->abort(__('legacy/offers.std_delete_offer'), __('legacy/offers.std_delete_offer_note')."<br /><form method=post action=offers.php?id={$offerId}&del_offer=1&sure=1>".__('legacy/offers.text_reason_is').'<input type=text name=reason><input type=submit value="'.__('legacy/offers.submit_confirm').'"></form>', false);
        }

        $reason = (string) $request->input('reason');
        $this->offerRepository->deleteOffer($offerId);
        $this->offerVoteRepository->deleteOfferVotes($offerId);
        $this->offerCommentRepository->deleteOfferComments($offerId);

        if ($userId !== (int) $num['userid']) {
            $locale = Locale::userLocale((int) $num['userid']);
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            $msg = Locale::trans('offer.msg_your_offer', [], $locale).$num['name'].Locale::trans('offer.msg_was_deleted_by', [], $locale)."[url=userdetails.php?id={$userId}]".($curuser['username'] ?? '').'[/url]'.Locale::trans('offer.msg_blank', [], $locale).($reason !== '' ? Locale::trans('offer.msg_reason_is', [], $locale).$reason : '');

            Message::add([
                'sender' => null,
                'receiver' => (int) $num['userid'],
                'msg' => $msg,
                'subject' => $subject,
                'added' => now(),
            ]);
        }

        Log::writeWithContext("Offer: {$offerId} ({$num['name']}) was deleted by ".($curuser['username'] ?? '').($reason !== '' ? " ({$reason})" : ''), 'normal');

        return redirect('/offers.php');
    }

    private function handleEdit(Request $request): RedirectResponse
    {
        if ((int) $request->input('take_off_edit') !== 1) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $id = (int) $request->input('id');
        if (! Validators::isId($id)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_smell_rat'));
        }

        $offerOwner = $this->offerRepository->getOfferOwner($id);
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($offerOwner !== $userId && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_access_denied'));
        }

        $name = (string) $request->input('name');

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_wrong_image_format'));
            }
            $pic = '[img]'.$picture."[/img]\n";
        }

        $descr = $pic.(string) Input::unescape($request->input('body'));
        if ($name === '') {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_enter_name'));
        }
        if ($descr === '') {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_enter_description'));
        }

        $cat = (int) $request->input('category');
        if (! Validators::isId($cat)) {
            $this->abort(__('legacy/offers.std_error'), __('legacy/offers.std_must_select_category'));
        }

        $this->offerRepository->updateOffer($id, [
            'category' => $cat,
            'name' => $name,
            'descr' => $descr,
        ]);

        return redirect("/offers.php?id={$id}&off_details=1");
    }
}
