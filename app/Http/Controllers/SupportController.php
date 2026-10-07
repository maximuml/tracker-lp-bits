<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\ComplainRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\ComplainNewRequest;
use App\Http\Requests\ComplainReplyRequest;
use App\Http\Requests\ComplainToggleRequest;
use App\Models\Setting;
use App\Services\ComplainService;
use App\Support\Captcha;
use App\Support\CurrentUser;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SupportController extends LegacyController
{
    public function __construct(
        private readonly ComplainRepositoryInterface $complainRepository,
        private readonly ComplainService $complainService,
        private readonly CurrentUser $currentUser,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function complains(Request $request): View|RedirectResponse|Response
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $uid = (int) ($this->currentUser->id());
        $isAdmin = Permission::can(PermissionEnum::STAFF_MEMBER);

        if ($uid > 0 && ! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }
        if (! $isAdmin && ! Setting::getIsComplainEnabled()) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.complain_not_enabled'));
        }

        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($action) && $isAdmin) {
            $action = 'list';
        }

        return match ($action) {
            'list' => $this->complainList($request, $isAdmin),
            'view' => $this->complainView($request, $uid, $isAdmin),
            default => $this->complainCompose($request, $uid),
        };
    }

    public function complainsPost(Request $request): RedirectResponse|Response
    {
        if (($abort = $this->complainsGate()) !== null) {
            return $abort;
        }

        return $this->handleComplainPost($request);
    }

    public function complainNewPost(ComplainNewRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->complainsGate()) !== null) {
            return $abort;
        }

        return $this->complainNew($request);
    }

    public function complainReplyPost(ComplainReplyRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->complainsGate()) !== null) {
            return $abort;
        }

        return $this->complainReply($request, $this->complainsUid());
    }

    public function complainAnsweredPost(ComplainToggleRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->complainsGate()) !== null) {
            return $abort;
        }

        return $this->complainToggle($request, Permission::can(PermissionEnum::STAFF_MEMBER), 'answered');
    }

    public function complainUnansweredPost(ComplainToggleRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->complainsGate()) !== null) {
            return $abort;
        }

        return $this->complainToggle($request, Permission::can(PermissionEnum::STAFF_MEMBER), 'unanswered');
    }

    private function complainsUid(): int
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);

        return (int) ($this->currentUser->id());
    }

    private function complainsGate(): ?Response
    {
        $uid = $this->complainsUid();
        $isAdmin = Permission::can(PermissionEnum::STAFF_MEMBER);

        if ($uid > 0 && ! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }
        if (! $isAdmin && ! Setting::getIsComplainEnabled()) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.complain_not_enabled'));
        }

        return null;
    }

    private function handleComplainPost(Request $request): RedirectResponse|Response
    {
        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        // Legacy callers can carry params in the URL — forward the query
        // string so the target endpoint still sees them.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return match ($action) {
            'new' => redirect()->to('/web/complains/new'.$suffix, 308),
            'reply' => redirect()->to('/web/complains/reply'.$suffix, 308),
            'answered' => redirect()->to('/web/complains/answered'.$suffix, 308),
            'unanswered' => redirect()->to('/web/complains/unanswered'.$suffix, 308),
            default => $this->legacyAbortResponse(__('functions.std_error'), 'Permission denied.'),
        };
    }

    private function complainNew(Request $request): RedirectResponse|Response
    {
        if (! Captcha::checkCode(
            (string) ($request->input('imagehash') ?? ''),
            (string) ($request->input('imagestring') ?? ''),
            '/web/complains',
            false,
            true,
        )) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.text_new_failure'));
        }

        $email = filter_var((string) ($request->input('email') ?? ''), FILTER_VALIDATE_EMAIL);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($email) || empty($body)) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.text_new_failure'));
        }

        $uuid = $this->complainService->createComplain($email, $body, Network::clientIp());
        if ($uuid === null) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.text_new_failure'));
        }

        return redirect('/web/complains?action=view&id='.urlencode($uuid));
    }

    private function complainReply(Request $request, int $uid): RedirectResponse|Response
    {
        $id = (int) $request->input('id', 0);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($id <= 0 || empty($body)) {
            return $this->legacyAbortResponse(__('functions.std_error'), __('complains.text_new_failure'));
        }

        if ($uid <= 0) {
            $uuid = filter_var((string) ($request->input('uuid') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $owns = $this->complainRepository->existsByIdAndUuid($id, $uuid);
            if (! $owns) {
                return $this->legacyAbortResponse(('Error'), 'Permission denied.');
            }
        }

        $ok = $this->complainService->replyToComplain($id, $uid, $body, Network::clientIp());
        if (! $ok) {
            return $this->legacyAbortResponse(__('functions.std_error'), 'Complain not found.');
        }

        return redirect()->to($request->headers->get('referer') ?: '/web/complains');
    }

    private function complainToggle(Request $request, bool $isAdmin, string $action): RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $this->complainService->toggleAnswered($id, $action === 'answered');

        return redirect()->to($request->headers->get('referer') ?: '/web/complains');
    }

    private function complainList(Request $request, bool $isAdmin): View|RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $pendingRows = [];
        if ($request->input('page') === null) {
            $pendingRows = $this->complainRepository->listPending();
        }

        $count = $this->complainRepository->countAnswered();
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager(20, $count, '?action=list&');
        $processedRows = $this->complainRepository->listAnswered($offset, $rpp);

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'list',
            'pendingRows' => $pendingRows,
            'processedRows' => $processedRows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'page' => $request->input('page'),
            'title' => __('complains.text_complain'),
            'isAdmin' => $isAdmin,
            'isLogin' => true,
        ]);
    }

    private function complainView(Request $request, int $uid, bool $isAdmin): View|RedirectResponse|Response
    {
        $uuid = filter_var((string) ($request->input('id') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (strlen($uuid) !== 36) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $complain = $this->complainRepository->findByUuid($uuid) ?? [];
        if (empty($complain)) {
            return $this->legacyAbortResponse(('Error'), 'Complain not found.');
        }

        $user = $this->userRepository->findByEmail((string) ($complain['email'] ?? ''), ['id', 'username']);

        $replyRows = $this->complainRepository->listReplies((int) ($complain['id'] ?? 0));

        $replyUserIds = array_filter(array_unique(array_column($replyRows, 'userid')));
        $replyUserMap = [];
        foreach ($replyUserIds as $rUid) {
            $replyUserMap[(int) $rUid] = UserDisplay::plainUsername((int) $rUid);
        }

        $replyBoxHtml = Html::quickReply('reply', 'body', __('complains.text_reply'));

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'view',
            'complain' => $complain,
            'user' => $user?->toArray(),
            'replyRows' => $replyRows,
            'replyUserMap' => $replyUserMap,
            'isAdmin' => $isAdmin,
            'isLogin' => $uid > 0,
            'title' => __('complains.text_complain'),
            'replyBoxHtml' => SafeHtml::fromTrustedHtml($replyBoxHtml),
        ]);
    }

    private function complainCompose(Request $request, int $uid): View|RedirectResponse
    {
        $captchaHtml = Captcha::renderHtml(layout: 'grid');

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'compose',
            'title' => __('complains.text_complain'),
            'captchaHtml' => SafeHtml::fromTrustedHtml($captchaHtml),
        ]);
    }
}
