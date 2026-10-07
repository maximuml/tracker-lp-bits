<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\MessageRepository;
use App\Support\Cache;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\Locale;
use App\Support\Log;
use App\Support\PageResponses;
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
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
        private readonly OfferCommentRepositoryInterface $offerCommentRepository,
        private readonly OfferModerationService $offerModerationService,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function handleActionPublic(Request $request): ?RedirectResponse
    {
        $action = $this->action($request);

        if ($action === '') {
            return null;
        }

        if (! $request->isMethod('post')) {
            return redirect('/web/offers');
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
        PageResponses::abort($heading, $text, $htmlstrip);
    }

    public function handleCreate(Request $request): RedirectResponse
    {
        Permission::assertCan(PermissionEnum::ADD_OFFER);

        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);
        if (! Validators::isId($userId)) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $name = (string) $request->input('name');
        if ($name === '') {
            $this->abort(__('offers.std_error'), __('offers.std_must_enter_name'));
        }

        $cat = (int) ($request->input('type') ?? $request->input('category'));
        if (! Validators::isId($cat)) {
            $this->abort(__('offers.std_error'), __('offers.std_must_select_category'));
        }

        $descrmain = (string) Input::unescape($request->input('body') ?? $request->input('descr'));
        if (! $descrmain) {
            $this->abort(__('offers.std_error'), __('offers.std_must_enter_description'));
        }

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort(__('offers.std_error'), __('offers.std_wrong_image_format'));
            }
            $pic = '[img]'.$picture."[/img]\n";
        }

        $descr = $pic.$descrmain;

        if ($this->offerRepository->offerNameExists($name)) {
            $this->abort(__('offers.std_error'), __('offers.std_offer_exists').view('components.altlink', ['class' => 'altlink', 'url' => '/web/offers', 'text' => __('offers.text_view_all_offers')])->render(), false);
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
            $this->abort(__('offers.std_error'), 'mysql puked');
        }

        $this->offerRepository->addStaffMessage($userId, (string) ($curuser['username'] ?? ''), $name, $id);
        Cache::clearStaffMessage();
        Log::writeWithContext("offer {$name} was added by ".($curuser['username'] ?? ''), 'normal');

        return redirect("/web/offers?id={$id}&off_details=1");
    }

    public function handleDelete(Request $request): RedirectResponse
    {
        $offerId = (int) $request->input('id');
        if (! Validators::isId($offerId)) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $offerRecord = $this->offerRepository->findOffer($offerId);
        if (! $offerRecord) {
            $this->abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        if ($offerRecord === null) {
            throw new LogicException('Expected non-null offer record.');
        }

        $num = $offerRecord->toArray();
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($userId !== (int) ($num['userid'] ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('offers.std_error'), __('offers.std_cannot_delete_others_offer'));
        }

        $sure = (int) $request->input('sure');
        if ($sure !== 0 && $sure !== 1) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        if ($sure === 0) {
            $this->abort(__('offers.std_delete_offer'), __('offers.std_delete_offer_note').view('offers.delete-confirm-form', ['url' => "/web/offers/delete?id={$offerId}&sure=1"])->render(), false);
        }

        $reason = (string) $request->input('reason');
        $this->offerRepository->deleteOffer($offerId);
        $this->offerVoteRepository->deleteOfferVotes($offerId);
        $this->offerCommentRepository->deleteOfferComments($offerId);

        if ($userId !== (int) ($num['userid'] ?? 0)) {
            $locale = Locale::userLocale((int) ($num['userid'] ?? 0));
            $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);
            $msg = Locale::trans('offer.msg_your_offer', [], $locale).($num['name'] ?? '').Locale::trans('offer.msg_was_deleted_by', [], $locale)."[url=/userdetails?id={$userId}]".($curuser['username'] ?? '').'[/url]'.Locale::trans('offer.msg_blank', [], $locale).($reason !== '' ? Locale::trans('offer.msg_reason_is', [], $locale).$reason : '');

            $this->messageRepository->add([
                'sender' => null,
                'receiver' => (int) ($num['userid'] ?? 0),
                'msg' => $msg,
                'subject' => $subject,
                'added' => now(),
            ]);
        }

        Log::writeWithContext('Offer: '.$offerId.' ('.($num['name'] ?? '').') was deleted by '.($curuser['username'] ?? '').($reason !== '' ? " ({$reason})" : ''), 'normal');

        return redirect('/web/offers');
    }

    public function handleEdit(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        if (! Validators::isId($id)) {
            $this->abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $offerOwner = $this->offerRepository->getOfferOwner($id);
        $curuser = $this->curUser();
        $userId = (int) ($curuser['id'] ?? 0);

        if ($offerOwner !== $userId && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $this->abort(__('offers.std_error'), __('offers.std_access_denied'));
        }

        $name = (string) $request->input('name');

        $pic = '';
        $picture = (string) $request->input('picture');
        if ($picture !== '') {
            $picture = (string) Input::unescape($picture);
            if (! preg_match('/^https?:\/\/[^\s\'"<>]+\.(jpg|gif|png)$/i', $picture)) {
                $this->abort(__('offers.std_error'), __('offers.std_wrong_image_format'));
            }
            $pic = '[img]'.$picture."[/img]\n";
        }

        $descr = $pic.(string) Input::unescape($request->input('body'));
        if ($name === '') {
            $this->abort(__('offers.std_error'), __('offers.std_must_enter_name'));
        }
        if ($descr === '') {
            $this->abort(__('offers.std_error'), __('offers.std_must_enter_description'));
        }

        $cat = (int) $request->input('category');
        if (! Validators::isId($cat)) {
            $this->abort(__('offers.std_error'), __('offers.std_must_select_category'));
        }

        $this->offerRepository->updateOffer($id, [
            'category' => $cat,
            'name' => $name,
            'descr' => $descr,
        ]);

        return redirect("/web/offers?id={$id}&off_details=1");
    }
}
